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

    public function resolveKrsKurikulumId(Mahasiswa|string|null $mahasiswa, ?int $targetSemester = null): ?string
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

        if ($targetSemester === null) {
            $targetSemester = $this->calculateTargetKurikulumSemester($resolvedMahasiswa, $semesterAktif);
        }

        $tahunAkademikAktif = $semesterAktif->tahunAkademik->tahun_akademik;
        $jenisSemesterAktif = $this->normalizeSemesterType($semesterAktif->nama_semester);
        $isRpl = $this->isRplMahasiswa($resolvedMahasiswa);
        $hasKeterangan = $this->hasKeteranganColumn();

        // 1. Query dasar kurikulum di prodi mahasiswa sesuai jalur RPL / Reguler
        $candidateQuery = Kurikulum::query()
            ->where('id_prodi', $resolvedMahasiswa->id_prodi);

        if ($isRpl) {
            $candidateQuery->where(function ($q) use ($hasKeterangan) {
                $q->where('nama_struktur_mk', 'like', '%RPL%');
                if ($hasKeterangan) {
                    $q->orWhere('keterangan', 'like', '%RPL%');
                }
            });
        } else {
            $candidateQuery->where('nama_struktur_mk', 'not like', '%RPL%');
            if ($hasKeterangan) {
                $candidateQuery->where(function ($q) {
                    $q->whereNull('keterangan')
                        ->orWhere('keterangan', 'not like', '%RPL%');
                });
            }
        }

        // Prioritas 1 (Murni Relasional Database):
        // Kurikulum yang memiliki baris mata kuliah di semester tempuh mahasiswa ($targetSemester)
        // DAN memiliki kelas kuliah yang dibuka pada semester aktif di prodi ini.
        if ($targetSemester > 0) {
            $level1 = (clone $candidateQuery)
                ->whereHas('kurikulumMataKuliah', function ($q) use ($targetSemester, $semesterAktif, $resolvedMahasiswa) {
                    $q->where('semester_ke', $targetSemester)
                        ->whereHas('kelasKuliah', function ($kq) use ($semesterAktif, $resolvedMahasiswa) {
                            $kq->where('id_semester', $semesterAktif->id)
                                ->where('id_prodi', $resolvedMahasiswa->id_prodi);
                        });
                })
                ->first();

            if ($level1) {
                return $level1->id;
            }

            // Prioritas 2 (Relasional Kurikulum-Mata Kuliah):
            // Kurikulum yang memiliki mata kuliah di semester tempuh mahasiswa ($targetSemester).
            $level2 = (clone $candidateQuery)
                ->whereHas('kurikulumMataKuliah', function ($q) use ($targetSemester) {
                    $q->where('semester_ke', $targetSemester);
                })
                ->first();

            if ($level2) {
                return $level2->id;
            }
        }

        // Prioritas 3 (Kesesuaian Periode Semester Mulai):
        $level3 = (clone $candidateQuery)
            ->whereHas('semesterMulai', function ($query) use ($tahunAkademikAktif, $jenisSemesterAktif) {
                $query->where('nama_semester', 'like', '%'.$jenisSemesterAktif.'%');
                $query->whereHas('tahunAkademik', function ($tahunAkademikQuery) use ($tahunAkademikAktif) {
                    $tahunAkademikQuery->where('tahun_akademik', $tahunAkademikAktif);
                });
            })
            ->first();

        if ($level3) {
            return $level3->id;
        }

        return $matchingId;
    }

    public function calculateTargetKurikulumSemester(Mahasiswa $mahasiswa, ?Semester $semester = null): int
    {
        if (! $semester) {
            $semester = Semester::query()
                ->with('tahunAkademik')
                ->where('status', 'Aktif')
                ->first();
        } else {
            $semester->loadMissing('tahunAkademik');
        }

        if (! $semester || ! $semester->tahunAkademik) {
            return 1;
        }

        $tahunMulai = (int) substr((string) $semester->tahunAkademik->tahun_akademik, 0, 4);
        $digitPeriode = strtolower(trim((string) $semester->nama_semester)) === 'ganjil' ? 1 : 2;
        $selisihTahun = $tahunMulai - (int) ($mahasiswa->angkatan ?? $tahunMulai);
        $semesterKe = max(1, ($selisihTahun * 2) + $digitPeriode);

        if ($this->isRplMahasiswa($mahasiswa) && ($mahasiswa->sks_diakui ?? 0) > 0) {
            $semesterEkuivalen = (int) floor($mahasiswa->sks_diakui / 20);
            $semesterKe += $semesterEkuivalen;
        }

        return max(1, $semesterKe);
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
