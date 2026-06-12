<?php

namespace App\Services\Krs;

use App\Models\Akademik\KRS;
use App\Models\Akademik\KRSDetail;
use App\Models\Akademik\KrsCollectiveBatchItem;
use App\Models\MasterData\KelasKuliah;
use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\Prodi;
use App\Models\MasterData\Semester;
use App\Models\MasterData\TahunAkademik;
use App\Services\ActiveCurriculumService;
use App\Services\CurriculumConversionService;
use App\Services\MahasiswaCurriculumContextService;
use Illuminate\Support\Collection;

class KrsHistoricalEligibilityService
{
    public function __construct(
        private readonly ActiveCurriculumService $activeCurriculumService,
        private readonly CurriculumConversionService $curriculumConversionService,
        private readonly MahasiswaCurriculumContextService $mahasiswaCurriculumContextService
    ) {
    }

    public function filters(): array
    {
        return [
            'tahun_akademik' => TahunAkademik::query()
                ->orderByDesc('tahun_akademik')
                ->get(),
            'semester' => Semester::query()
                ->with('tahunAkademik')
                ->orderByDesc('tanggal_mulai')
                ->get(),
            'prodi' => Prodi::query()
                ->orderBy('nama_prodi')
                ->get(),
            'semester_ke_options' => collect(range(1, 14))->map(fn(int $semesterKe) => [
                'value' => $semesterKe,
                'label' => 'Semester ' . $semesterKe,
            ])->all(),
        ];
    }

    public function eligibleStudents(array $filters): Collection
    {
        $semester = Semester::with('tahunAkademik')->findOrFail($filters['id_semester']);

        $query = Mahasiswa::query()
            ->with([
                'prodi:id,nama_prodi',
                'riwayatKurikulumAktif:id,id_mahasiswa,id_kurikulum,is_active,tanggal_mulai',
            ])
            ->where(function ($builder) {
                $builder->whereNull('status')
                    ->orWhere('status', '!=', 'nonaktif');
            });

        if (!empty($filters['id_prodi'])) {
            $query->where('id_prodi', $filters['id_prodi']);
        }

        if (!empty($filters['angkatan'])) {
            $query->where('angkatan', $filters['angkatan']);
        }

        if (!empty($filters['id_mahasiswa']) && is_array($filters['id_mahasiswa'])) {
            $query->whereIn('id', $filters['id_mahasiswa']);
        }

        $students = $query
            ->orderBy('angkatan')
            ->orderBy('nim')
            ->get();

        $semesterId = $semester->id;
        $studentIds = $students->pluck('id')->all();
        $prodiIds = $students->pluck('id_prodi')->filter()->unique()->all();

        $existingKrs = KRS::query()
            ->where('id_semester', $semesterId)
            ->whereIn('id_mahasiswa', $studentIds)
            ->get()
            ->keyBy('id_mahasiswa');

        $availableClassesPerProdi = KelasKuliah::query()
            ->where('id_semester', $semesterId)
            ->whereIn('id_prodi', $prodiIds)
            ->selectRaw('id_prodi, count(*) as total_kelas')
            ->groupBy('id_prodi')
            ->pluck('total_kelas', 'id_prodi');

        return $students->map(function (Mahasiswa $mahasiswa) use ($existingKrs, $availableClassesPerProdi, $semester) {
            $existing = $existingKrs->get($mahasiswa->id);
            $availableClasses = (int) ($availableClassesPerProdi[$mahasiswa->id_prodi] ?? 0);
            $hasClasses = $availableClasses > 0;
            $hasExisting = $existing !== null;
            $semesterTarget = $this->calculateHistoricalSemester($mahasiswa->angkatan, $semester);
            $resolvedKurikulumId = $this->mahasiswaCurriculumContextService->resolveMahasiswaKurikulumId($mahasiswa);
            $resolvedKurikulumIndukId = $this->mahasiswaCurriculumContextService->resolveMahasiswaKurikulumIndukId($mahasiswa);
            $resolvedOperationalKurikulumId = $this->activeCurriculumService->resolveActiveKurikulumId($mahasiswa);
            $resolvedOperationalKurikulum = $this->activeCurriculumService->resolveActiveKurikulum($mahasiswa);
            $resolvedKurikulumInduk = $resolvedOperationalKurikulum?->kurikulumInduk;
            $messages = [];

            if ($hasExisting) {
                $messages[] = 'Mahasiswa sudah memiliki KRS pada semester historis ini';
            }

            if (!$hasClasses) {
                $messages[] = 'Kelas kuliah historis untuk prodi dan semester ini belum tersedia';
            }

            return [
                'id' => $mahasiswa->id,
                'nim' => $mahasiswa->nim,
                'nama_mahasiswa' => $mahasiswa->nama_mahasiswa,
                'angkatan' => $mahasiswa->angkatan,
                'id_prodi' => $mahasiswa->id_prodi,
                'prodi' => $mahasiswa->prodi,
                'id_kurikulum_induk' => $resolvedKurikulumIndukId,
                'id_struktur_operasional' => $resolvedOperationalKurikulumId,
                'id_kurikulum_mahasiswa' => $resolvedKurikulumId,
                'id_kurikulum_operasional' => $resolvedOperationalKurikulumId,
                'id_kurikulum_dasar' => $resolvedKurikulumIndukId,
                'kurikulum_context' => [
                    'id_kurikulum_induk' => $resolvedKurikulumIndukId,
                    'id_struktur_operasional' => $resolvedOperationalKurikulumId,
                    'id_kurikulum_mahasiswa' => $resolvedKurikulumId,
                    'kurikulum_induk' => $resolvedKurikulumInduk ? [
                        'id' => $resolvedKurikulumInduk->id,
                        'nama_kurikulum' => $resolvedKurikulumInduk->nama_kurikulum,
                        'keterangan' => $resolvedKurikulumInduk->nama_kurikulum,
                        'kode_kurikulum' => $resolvedKurikulumInduk->kode_kurikulum,
                        'tahun_kurikulum' => $resolvedKurikulumInduk->tahun_kurikulum,
                        'jenis_kurikulum' => $resolvedKurikulumInduk->jenisKurikulum ? [
                            'id' => $resolvedKurikulumInduk->jenisKurikulum->id,
                            'kode_jenis' => $resolvedKurikulumInduk->jenisKurikulum->kode_jenis,
                            'nama_jenis_kurikulum' => $resolvedKurikulumInduk->jenisKurikulum->nama_jenis_kurikulum,
                        ] : null,
                    ] : null,
                    'struktur_operasional' => $resolvedOperationalKurikulum ? [
                        'id' => $resolvedOperationalKurikulum->id,
                        'nama_struktur_mk' => $resolvedOperationalKurikulum->display_name,
                        'nama_kurikulum' => $resolvedOperationalKurikulum->nama_kurikulum,
                        'id_kurikulum_induk' => $resolvedOperationalKurikulum->id_kurikulum_induk,
                        'mulai_berlaku' => $resolvedOperationalKurikulum->semesterMulai?->tahunAkademik
                            ? trim($resolvedOperationalKurikulum->semesterMulai->tahunAkademik->tahun_akademik . ' ' . $resolvedOperationalKurikulum->semesterMulai->nama_semester)
                            : null,
                    ] : null,
                ],
                'semester_target' => $semesterTarget,
                'existing_historical_krs' => $existing ? [
                    'id' => $existing->id,
                    'status_approval' => $existing->status_approval,
                    'is_locked' => (bool) $existing->is_locked,
                    'total_sks' => $existing->total_sks,
                ] : null,
                'available_class_count' => $availableClasses,
                'is_ready' => !$hasExisting && $hasClasses,
                'default_action' => $hasExisting ? KrsCollectiveBatchItem::STATUS_SKIPPED : ($hasClasses ? KrsCollectiveBatchItem::STATUS_READY : KrsCollectiveBatchItem::STATUS_FAILED),
                'message' => empty($messages) ? 'Mahasiswa siap diproses pada semester historis ini' : implode('; ', $messages),
            ];
        })->values();
    }

    public function packageClasses(array $filters): Collection
    {
        return KelasKuliah::query()
            ->with([
                'kurikulumMataKuliah.kurikulum:id,id_kurikulum_induk,nama_struktur_mk',
                'kurikulumMataKuliah.kurikulum.kurikulumInduk:id,nama_kurikulum,kode_kurikulum,tahun_kurikulum,id_jenis_kurikulum',
                'kurikulumMataKuliah.kurikulum.kurikulumInduk.jenisKurikulum:id,kode_jenis,nama_jenis_kurikulum',
                'kurikulumMataKuliah.mataKuliah:id,kode_mk,nama_mk,sks',
            ])
            ->where('id_semester', $filters['id_semester'])
            ->where('id_prodi', $filters['id_prodi'])
            ->whereHas('kurikulumMataKuliah', function ($query) use ($filters) {
                $query->where('semester_ke', $filters['semester_ke']);
            })
            ->get()
            ->sortBy([
                fn(KelasKuliah $kelas) => $kelas->kurikulumMataKuliah?->kurikulum?->display_name ?? '',
                fn(KelasKuliah $kelas) => $kelas->kurikulumMataKuliah?->mataKuliah?->kode_mk ?? '',
                fn(KelasKuliah $kelas) => $kelas->nama_kelas ?? '',
            ])
            ->values()
            ->map(function (KelasKuliah $kelas) {
                return [
                    'id' => $kelas->id,
                    'nama_kelas' => $kelas->nama_kelas,
                    'id_struktur_operasional' => $kelas->kurikulumMataKuliah?->id_kurikulum,
                    'id_kurikulum_induk' => $kelas->kurikulumMataKuliah?->kurikulum?->id_kurikulum_induk,
                    'id_kurikulum' => $kelas->kurikulumMataKuliah?->id_kurikulum,
                    'nama_struktur_operasional' => $kelas->kurikulumMataKuliah?->kurikulum?->display_name,
                    'nama_kurikulum_induk' => $kelas->kurikulumMataKuliah?->kurikulum?->kurikulumInduk?->nama_kurikulum,
                    'nama_kurikulum' => $kelas->kurikulumMataKuliah?->kurikulum?->display_name,
                    'kurikulum_context' => [
                        'id_kurikulum_induk' => $kelas->kurikulumMataKuliah?->kurikulum?->id_kurikulum_induk,
                        'id_struktur_operasional' => $kelas->kurikulumMataKuliah?->id_kurikulum,
                        'kurikulum_induk' => $kelas->kurikulumMataKuliah?->kurikulum?->kurikulumInduk ? [
                            'id' => $kelas->kurikulumMataKuliah->kurikulum->kurikulumInduk->id,
                            'nama_kurikulum' => $kelas->kurikulumMataKuliah->kurikulum->kurikulumInduk->nama_kurikulum,
                            'keterangan' => $kelas->kurikulumMataKuliah->kurikulum->kurikulumInduk->nama_kurikulum,
                            'kode_kurikulum' => $kelas->kurikulumMataKuliah->kurikulum->kurikulumInduk->kode_kurikulum,
                            'tahun_kurikulum' => $kelas->kurikulumMataKuliah->kurikulum->kurikulumInduk->tahun_kurikulum,
                            'jenis_kurikulum' => $kelas->kurikulumMataKuliah->kurikulum->kurikulumInduk->jenisKurikulum ? [
                                'id' => $kelas->kurikulumMataKuliah->kurikulum->kurikulumInduk->jenisKurikulum->id,
                                'kode_jenis' => $kelas->kurikulumMataKuliah->kurikulum->kurikulumInduk->jenisKurikulum->kode_jenis,
                                'nama_jenis_kurikulum' => $kelas->kurikulumMataKuliah->kurikulum->kurikulumInduk->jenisKurikulum->nama_jenis_kurikulum,
                            ] : null,
                        ] : null,
                        'struktur_operasional' => $kelas->kurikulumMataKuliah?->kurikulum ? [
                            'id' => $kelas->kurikulumMataKuliah->kurikulum->id,
                            'nama_struktur_mk' => $kelas->kurikulumMataKuliah->kurikulum->display_name,
                            'nama_kurikulum' => $kelas->kurikulumMataKuliah->kurikulum->nama_kurikulum,
                            'mulai_berlaku' => $kelas->kurikulumMataKuliah->kurikulum->semesterMulai?->tahunAkademik
                                ? trim($kelas->kurikulumMataKuliah->kurikulum->semesterMulai->tahunAkademik->tahun_akademik . ' ' . $kelas->kurikulumMataKuliah->kurikulum->semesterMulai->nama_semester)
                                : null,
                        ] : null,
                    ],
                    'semester_ke' => (int) ($kelas->kurikulumMataKuliah?->semester_ke ?? 0),
                    'mata_kuliah' => [
                        'id' => $kelas->kurikulumMataKuliah?->mataKuliah?->id,
                        'kode_mk' => $kelas->kurikulumMataKuliah?->mataKuliah?->kode_mk,
                        'nama_mk' => $kelas->kurikulumMataKuliah?->mataKuliah?->nama_mk,
                        'sks' => (int) ($kelas->kurikulumMataKuliah?->mataKuliah?->sks ?? 0),
                    ],
                ];
            });
    }

    public function repeatCandidates(array $filters): Collection
    {
        $semester = Semester::with('tahunAkademik')->findOrFail($filters['id_semester']);
        $studentIds = collect($filters['id_mahasiswa'] ?? [])
            ->filter()
            ->values();

        if ($studentIds->isEmpty()) {
            return collect();
        }

        $students = Mahasiswa::query()
            ->with([
                'prodi:id,nama_prodi',
                'riwayatKurikulum:id,id_mahasiswa,id_kurikulum,is_active,tanggal_mulai',
            ])
            ->whereIn('id', $studentIds->all())
            ->get()
            ->keyBy('id');

        $packageMataKuliahIdsByProdi = KelasKuliah::query()
            ->with('kurikulumMataKuliah:id,id_mata_kuliah,semester_ke')
            ->where('id_semester', $semester->id)
            ->whereHas('kurikulumMataKuliah', function ($query) use ($filters) {
                $query->where('semester_ke', $filters['semester_ke']);
            })
            ->get()
            ->groupBy('id_prodi')
            ->map(function (Collection $classes) {
                return $classes
                    ->pluck('kurikulumMataKuliah.id_mata_kuliah')
                    ->filter()
                    ->unique()
                    ->values();
            });

        return $studentIds->map(function (string $studentId) use ($students, $semester, $packageMataKuliahIdsByProdi) {
            /** @var Mahasiswa|null $student */
            $student = $students->get($studentId);

            if (!$student) {
                return [
                    'id_mahasiswa' => $studentId,
                    'courses' => [],
                ];
            }

            $packageMataKuliahIds = collect($packageMataKuliahIdsByProdi->get($student->id_prodi, collect()))
                ->filter()
                ->unique()
                ->values();

            $failedHistories = KRSDetail::query()
                ->whereHas('krs', function ($query) use ($student, $semester) {
                    $query->where('id_mahasiswa', $student->id)
                        ->where('status_approval', KRS::STATUS_APPROVED)
                        ->where('id_semester', '!=', $semester->id);
                })
                ->where('status', KRSDetail::STATUS_TIDAK_LULUS)
                ->with([
                    'mataKuliah',
                    'kelasKuliah.kurikulumMataKuliah.mataKuliah',
                    'krs.semester.tahunAkademik',
                ])
                ->get()
                ->groupBy(function (KRSDetail $detail) use ($student) {
                    $sourceCourseId = $detail->id_mata_kuliah
                        ?? $detail->mataKuliah?->id
                        ?? $detail->kelasKuliah?->kurikulumMataKuliah?->mataKuliah?->id;
                    if (!filled($sourceCourseId)) {
                        return null;
                    }

                    return $this->curriculumConversionService
                        ->resolveTranscriptCourse($student->id, $sourceCourseId)?->id
                        ?? $sourceCourseId;
                })
                ->map(function (Collection $items) use ($semester) {
                    return $items->filter(function (KRSDetail $detail) use ($semester) {
                        return $this->isSemesterBefore(
                            $detail->krs?->semester,
                            $semester
                        );
                    });
                })
                ->filter(fn(Collection $items) => $items->isNotEmpty())
                ->filter(fn($items, $mataKuliahId) => filled($mataKuliahId));

            $courses = $failedHistories->map(function (Collection $histories, $mataKuliahId) use ($student, $semester, $packageMataKuliahIds) {
                if ($packageMataKuliahIds->contains($mataKuliahId)) {
                    return null;
                }

                /** @var KRSDetail|null $latestHistory */
                $latestHistory = $histories->sortByDesc(function (KRSDetail $detail) {
                    return optional($detail->krs)->tanggal_pengajuan ?? $detail->created_at;
                })->first();

                $sourceCourseId = $latestHistory?->id_mata_kuliah
                    ?? $latestHistory?->mataKuliah?->id
                    ?? $latestHistory?->kelasKuliah?->kurikulumMataKuliah?->mataKuliah?->id;
                $mataKuliah = filled($sourceCourseId)
                    ? $this->curriculumConversionService->resolveTranscriptCourse($student->id, $sourceCourseId)
                    : null;

                if (!$mataKuliah) {
                    return null;
                }

                $availableClasses = KelasKuliah::query()
                    ->where('id_semester', $semester->id)
                    ->where('id_prodi', $student->id_prodi)
                    ->whereHas('kurikulumMataKuliah', function ($query) use ($mataKuliahId) {
                        $query->where('id_mata_kuliah', $mataKuliahId);
                    })
                    ->with('kurikulumMataKuliah.mataKuliah')
                    ->orderBy('nama_kelas')
                    ->get()
                    ->map(function (KelasKuliah $class) {
                        return [
                            'id' => $class->id,
                            'nama_kelas' => $class->nama_kelas,
                            'semester_ke' => (int) ($class->kurikulumMataKuliah?->semester_ke ?? 0),
                            'mata_kuliah' => [
                                'id' => $class->kurikulumMataKuliah?->mataKuliah?->id,
                                'kode_mk' => $class->kurikulumMataKuliah?->mataKuliah?->kode_mk,
                                'nama_mk' => $class->kurikulumMataKuliah?->mataKuliah?->nama_mk,
                                'sks' => (int) ($class->kurikulumMataKuliah?->mataKuliah?->sks ?? 0),
                            ],
                        ];
                    })
                    ->values();

                $riwayatSemester = $latestHistory?->krs?->semester;
                $riwayatTahun = $riwayatSemester?->tahunAkademik?->tahun_akademik ?? '';
                $riwayatLabel = trim((string) (($riwayatSemester?->nama_semester ?? '-') . ' ' . $riwayatTahun));

                return [
                    'id_mata_kuliah' => $mataKuliah->id,
                    'kode_mk' => $mataKuliah->kode_mk,
                    'nama_mk' => $mataKuliah->nama_mk,
                    'sks' => (int) ($mataKuliah->sks ?? 0),
                    'riwayat_terakhir' => [
                        'semester_label' => $riwayatLabel,
                        'status' => $latestHistory?->status,
                        'nilai_huruf' => $latestHistory?->nilai_huruf,
                        'nilai_akhir' => $latestHistory?->nilai_akhir,
                    ],
                    'available_classes' => $availableClasses->all(),
                    'availability_reason' => $availableClasses->isEmpty()
                        ? 'Belum ada kelas aktif untuk mata kuliah ini pada semester yang dipilih.'
                        : null,
                ];
            })->filter()->values()->all();

            return [
                'id_mahasiswa' => $student->id,
                'courses' => $courses,
            ];
        })->values();
    }

    private function calculateHistoricalSemester(?int $angkatan, Semester $semester): int
    {
        if (!$angkatan) {
            return 0;
        }

        $tahunMulai = (int) substr((string) $semester->tahunAkademik?->tahun_akademik, 0, 4);
        $digitPeriode = strtolower(trim((string) $semester->nama_semester)) === 'ganjil' ? 1 : 2;
        $selisihTahun = $tahunMulai - $angkatan;

        return max(1, ($selisihTahun * 2) + $digitPeriode);
    }

    private function isSemesterBefore(?Semester $candidate, Semester $reference): bool
    {
        if (!$candidate || !$candidate->tahunAkademik || !$reference->tahunAkademik) {
            return true;
        }

        $candidateYear = (int) substr((string) $candidate->tahunAkademik->tahun_akademik, 0, 4);
        $referenceYear = (int) substr((string) $reference->tahunAkademik->tahun_akademik, 0, 4);

        if ($candidateYear !== $referenceYear) {
            return $candidateYear < $referenceYear;
        }

        return $this->semesterPeriodWeight($candidate->nama_semester) < $this->semesterPeriodWeight($reference->nama_semester);
    }

    private function semesterPeriodWeight(?string $semesterName): int
    {
        $normalized = strtolower(trim((string) $semesterName));

        return str_contains($normalized, 'genap') ? 2 : 1;
    }
}
