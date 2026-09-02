<?php

namespace App\Services;

use App\Models\MasterData\Kurikulum;
use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\Semester;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MahasiswaCurriculumContextService
{
    public function resolveMahasiswaKurikulumId(Mahasiswa|string|null $mahasiswa): ?string
    {
        $resolvedMahasiswa = $this->resolveMahasiswa($mahasiswa);
        if (!$resolvedMahasiswa) {
            return null;
        }

        // Mahasiswa tidak lagi di-assign ke kurikulum tertentu; struktur
        // kurikulum dipilih berdasarkan prodi + angkatan mahasiswa.
        return $this->resolveMatchingKurikulumId(
            $resolvedMahasiswa->id_prodi,
            $resolvedMahasiswa->angkatan
        );
    }

    public function resolveKrsKurikulumId(Mahasiswa|string|null $mahasiswa): ?string
    {
        $resolvedMahasiswa = $this->resolveMahasiswa($mahasiswa);
        if (!$resolvedMahasiswa) {
            return null;
        }

        $matchingId = $this->resolveMahasiswaKurikulumId($resolvedMahasiswa);
        if (!$matchingId) {
            return null;
        }

        $semesterAktif = Semester::query()
            ->with('tahunAkademik')
            ->where('status', 'Aktif')
            ->first();

        if (!$semesterAktif || !$semesterAktif->tahunAkademik) {
            return $matchingId;
        }

        // Preferensi struktur yang semesterMulai-nya cocok dengan tahun
        // akademik + jenis semester aktif; jika tidak ada, fallback ke
        // hasil matching prodi/angkatan.
        $tahunAkademikAktif = $semesterAktif->tahunAkademik->tahun_akademik;
        $jenisSemesterAktif = $this->normalizeSemesterType($semesterAktif->nama_semester);

        $matchedByPeriod = Kurikulum::query()
            ->where('id_prodi', $resolvedMahasiswa->id_prodi)
            ->whereHas('semesterMulai', function ($query) use ($tahunAkademikAktif, $jenisSemesterAktif) {
                $query->where('nama_semester', 'like', '%' . $jenisSemesterAktif . '%');
                $query->whereHas('tahunAkademik', function ($tahunAkademikQuery) use ($tahunAkademikAktif) {
                    $tahunAkademikQuery->where('tahun_akademik', $tahunAkademikAktif);
                });
            })
            ->orderBy('nama_struktur_mk')
            ->orderBy('id')
            ->first();

        return $matchedByPeriod?->id ?? $matchingId;
    }

    public function resolveRequestedOrMatchingKurikulumId(
        ?string $requestedKurikulumId,
        ?string $prodiId,
        $angkatan = null,
        $tanggalMasuk = null
    ): ?string {
        if (!$prodiId) {
            return null;
        }

        if (filled($requestedKurikulumId)) {
            $kurikulum = Kurikulum::query()
                ->where('id', $requestedKurikulumId)
                ->where('id_prodi', $prodiId)
                ->first();

            if (!$kurikulum) {
                throw ValidationException::withMessages([
                    'id_kurikulum' => ['Kurikulum yang dipilih tidak sesuai dengan program studi mahasiswa.'],
                ]);
            }

            return $kurikulum->id;
        }

        return $this->resolveMatchingKurikulumId($prodiId, $angkatan, $tanggalMasuk);
    }

    public function resolveMatchingKurikulumId(?string $prodiId, $angkatan = null, $tanggalMasuk = null): ?string
    {
        if (!$prodiId) {
            return null;
        }

        $cohortSortKey = $this->resolveCohortSortKey($angkatan);
        $kurikulums = Kurikulum::with('semesterMulai.tahunAkademik')
            ->where('id_prodi', $prodiId)
            ->get();

        if ($kurikulums->isEmpty()) {
            return null;
        }

        $sortedKurikulums = $kurikulums
            ->sortByDesc(fn(Kurikulum $kurikulum) => $this->buildKurikulumSortKey($kurikulum) ?? 0)
            ->values();

        $preferredSemesterOrder = $this->resolvePreferredSemesterOrder($angkatan);

        if ($cohortSortKey !== null) {
            $eligibleKurikulums = $sortedKurikulums
                ->filter(function (Kurikulum $kurikulum) use ($cohortSortKey) {
                    $kurikulumSortKey = $this->buildKurikulumSortKey($kurikulum);

                    return $kurikulumSortKey !== null && $kurikulumSortKey <= $cohortSortKey;
                })
                ->values();

            if ($eligibleKurikulums->isNotEmpty()) {
                return $this->resolvePreferredKurikulumCandidate($eligibleKurikulums, $preferredSemesterOrder)?->id;
            }
        }

        return $this->resolvePreferredKurikulumCandidate($sortedKurikulums, $preferredSemesterOrder)?->id;
    }

    private function resolveMahasiswa(Mahasiswa|string|null $mahasiswa): ?Mahasiswa
    {
        if ($mahasiswa instanceof Mahasiswa) {
            return $mahasiswa;
        }

        if (!filled($mahasiswa)) {
            return null;
        }

        return Mahasiswa::find($mahasiswa);
    }

    private function resolveCohortSortKey($angkatan = null): ?int
    {
        if (filled($angkatan)) {
            return ((int) $angkatan * 10) + 1;
        }

        return null;
    }

    private function resolvePreferredSemesterOrder($angkatan = null): ?int
    {
        $cohortSortKey = $this->resolveCohortSortKey($angkatan);

        return $cohortSortKey !== null ? (int) substr((string) $cohortSortKey, -1) : null;
    }

    private function buildKurikulumSortKey(Kurikulum $kurikulum): ?int
    {
        $tahunAkademik = $kurikulum->semesterMulai?->tahunAkademik?->tahun_akademik;
        if (!$tahunAkademik) {
            return null;
        }

        $tahunMulai = (int) substr((string) $tahunAkademik, 0, 4);
        $semesterOrder = $this->resolveSemesterOrder(
            $kurikulum->semesterMulai?->kode_semester,
            $kurikulum->semesterMulai?->nama_semester
        );

        return ($tahunMulai * 10) + $semesterOrder;
    }

    private function resolveSemesterOrder(?string $kodeSemester = null, ?string $namaSemester = null): int
    {
        $normalizedKode = strtolower(trim((string) $kodeSemester));
        $normalizedNama = strtolower(trim((string) $namaSemester));

        if (str_contains($normalizedKode, 'ganjil') || str_contains($normalizedNama, 'ganjil') || $normalizedKode === '1') {
            return 1;
        }

        if (str_contains($normalizedKode, 'genap') || str_contains($normalizedNama, 'genap') || $normalizedKode === '2') {
            return 2;
        }

        return 9;
    }

    private function resolvePreferredKurikulumCandidate(Collection $kurikulums, ?int $preferredSemesterOrder): ?Kurikulum
    {
        if ($kurikulums->isEmpty()) {
            return null;
        }

        if ($preferredSemesterOrder !== null) {
            $preferred = $kurikulums->first(function (Kurikulum $kurikulum) use ($preferredSemesterOrder) {
                return $this->resolveSemesterOrder(
                    $kurikulum->semesterMulai?->kode_semester,
                    $kurikulum->semesterMulai?->nama_semester
                ) === $preferredSemesterOrder;
            });

            if ($preferred) {
                return $preferred;
            }
        }

        return $kurikulums->first();
    }

    private function normalizeSemesterType(?string $namaSemester): string
    {
        $normalized = strtolower(trim((string) $namaSemester));

        return match (true) {
            str_contains($normalized, 'ganjil') => 'ganjil',
            str_contains($normalized, 'genap') => 'genap',
            default => $normalized,
        };
    }
}
