<?php

namespace App\Services;

use App\Models\MasterData\Kurikulum;
use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\Semester;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MahasiswaCurriculumContextService
{
    public function resolveMahasiswaKurikulumId(Mahasiswa|string|null $mahasiswa): ?string
    {
        $resolvedMahasiswa = $this->resolveMahasiswa($mahasiswa);
        if (! $resolvedMahasiswa) {
            return null;
        }

        $isRpl = $this->isRplMahasiswa($resolvedMahasiswa);

        // Mahasiswa tidak lagi di-assign ke kurikulum tertentu; struktur
        // kurikulum dipilih berdasarkan prodi + angkatan mahasiswa + jalur RPL.
        return $this->resolveMatchingKurikulumId(
            $resolvedMahasiswa->id_prodi,
            $resolvedMahasiswa->angkatan,
            null,
            $isRpl
        );
    }

    public function resolveKrsKurikulumId(Mahasiswa|string|null $mahasiswa): ?string
    {
        $resolvedMahasiswa = $this->resolveMahasiswa($mahasiswa);
        if (! $resolvedMahasiswa) {
            return null;
        }

        $matchingId = $this->resolveMahasiswaKurikulumId($resolvedMahasiswa);
        if (! $matchingId) {
            return null;
        }

        $semesterAktif = Semester::query()
            ->with('tahunAkademik')
            ->where('status', 'Aktif')
            ->first();

        if (! $semesterAktif || ! $semesterAktif->tahunAkademik) {
            return $matchingId;
        }

        // Preferensi struktur yang semesterMulai-nya cocok dengan tahun
        // akademik + jenis semester aktif; jika tidak ada, fallback ke
        // hasil matching prodi/angkatan.
        $tahunAkademikAktif = $semesterAktif->tahunAkademik->tahun_akademik;
        $jenisSemesterAktif = $this->normalizeSemesterType($semesterAktif->nama_semester);
        $isRpl = $this->isRplMahasiswa($resolvedMahasiswa);

        $matchedByPeriodQuery = Kurikulum::query()
            ->where('id_prodi', $resolvedMahasiswa->id_prodi)
            ->whereHas('semesterMulai', function ($query) use ($tahunAkademikAktif, $jenisSemesterAktif) {
                $query->where('nama_semester', 'like', '%'.$jenisSemesterAktif.'%');
                $query->whereHas('tahunAkademik', function ($tahunAkademikQuery) use ($tahunAkademikAktif) {
                    $tahunAkademikQuery->where('tahun_akademik', $tahunAkademikAktif);
                });
            });

        $hasKeterangan = $this->hasKeteranganColumn();

        if ($isRpl) {
            $matchedByPeriod = (clone $matchedByPeriodQuery)
                ->where(function ($q) use ($hasKeterangan) {
                    $q->where('nama_struktur_mk', 'like', '%RPL%');
                    if ($hasKeterangan) {
                        $q->orWhere('keterangan', 'like', '%RPL%');
                    }
                })
                ->orderBy('nama_struktur_mk')
                ->orderBy('id')
                ->first();
        } else {
            $matchedByPeriod = (clone $matchedByPeriodQuery)
                ->where('nama_struktur_mk', 'not like', '%RPL%');

            if ($hasKeterangan) {
                $matchedByPeriod = $matchedByPeriod->where(function ($q) {
                    $q->whereNull('keterangan')
                        ->orWhere('keterangan', 'not like', '%RPL%');
                });
            }

            $matchedByPeriod = $matchedByPeriod
                ->orderBy('nama_struktur_mk')
                ->orderBy('id')
                ->first();
        }

        if (! $matchedByPeriod) {
            $matchedByPeriod = $matchedByPeriodQuery
                ->orderBy('nama_struktur_mk')
                ->orderBy('id')
                ->first();
        }

        return $matchedByPeriod?->id ?? $matchingId;
    }

    public function resolveRequestedOrMatchingKurikulumId(
        ?string $requestedKurikulumId,
        ?string $prodiId,
        $angkatan = null,
        $tanggalMasuk = null,
        bool $isRpl = false
    ): ?string {
        if (! $prodiId) {
            return null;
        }

        if (filled($requestedKurikulumId)) {
            $kurikulum = Kurikulum::query()
                ->where('id', $requestedKurikulumId)
                ->where('id_prodi', $prodiId)
                ->first();

            if (! $kurikulum) {
                throw ValidationException::withMessages([
                    'id_kurikulum' => ['Kurikulum yang dipilih tidak sesuai dengan program studi mahasiswa.'],
                ]);
            }

            return $kurikulum->id;
        }

        return $this->resolveMatchingKurikulumId($prodiId, $angkatan, $tanggalMasuk, $isRpl);
    }

    public function resolveMatchingKurikulumId(?string $prodiId, $angkatan = null, $tanggalMasuk = null, bool $isRpl = false): ?string
    {
        if (! $prodiId) {
            return null;
        }

        $cohortSortKey = $this->resolveCohortSortKey($angkatan);
        $baseQuery = Kurikulum::with('semesterMulai.tahunAkademik')
            ->where('id_prodi', $prodiId);

        $hasKeterangan = $this->hasKeteranganColumn();

        if ($isRpl) {
            $rplKurikulums = (clone $baseQuery)
                ->where(function ($q) use ($hasKeterangan) {
                    $q->where('nama_struktur_mk', 'like', '%RPL%');
                    if ($hasKeterangan) {
                        $q->orWhere('keterangan', 'like', '%RPL%');
                    }
                })
                ->get();
            $kurikulums = $rplKurikulums->isNotEmpty() ? $rplKurikulums : $baseQuery->get();
        } else {
            $regKurikulums = (clone $baseQuery)
                ->where('nama_struktur_mk', 'not like', '%RPL%');

            if ($hasKeterangan) {
                $regKurikulums = $regKurikulums->where(function ($q) {
                    $q->whereNull('keterangan')
                        ->orWhere('keterangan', 'not like', '%RPL%');
                });
            }

            $regKurikulums = $regKurikulums->get();
            $kurikulums = $regKurikulums->isNotEmpty() ? $regKurikulums : $baseQuery->get();
        }

        if ($kurikulums->isEmpty()) {
            return null;
        }

        $sortedKurikulums = $kurikulums
            ->sortByDesc(fn (Kurikulum $kurikulum) => $this->buildKurikulumSortKey($kurikulum) ?? 0)
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

    public function isRplMahasiswa(Mahasiswa|string|null $mahasiswa): bool
    {
        $resolved = $this->resolveMahasiswa($mahasiswa);
        if (! $resolved) {
            return false;
        }

        return in_array($resolved->jenis_pendaftaran, ['RPL', 'Pindahan'], true)
            || str_ends_with(strtoupper(trim((string) $resolved->nim)), 'B')
            || strtoupper(trim((string) ($resolved->jalur_masuk ?? ''))) === 'RPL';
    }

    private function resolveMahasiswa(Mahasiswa|string|null $mahasiswa): ?Mahasiswa
    {
        if ($mahasiswa instanceof Mahasiswa) {
            return $mahasiswa;
        }

        if (! filled($mahasiswa)) {
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
        if (! $tahunAkademik) {
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

    private function hasKeteranganColumn(): bool
    {
        static $hasColumn = null;

        if ($hasColumn === null) {
            $hasColumn = Schema::hasColumn('kurikulum', 'keterangan');
        }

        return $hasColumn;
    }
}
