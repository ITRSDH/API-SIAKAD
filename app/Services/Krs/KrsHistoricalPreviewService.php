<?php

namespace App\Services\Krs;

use App\Models\Akademik\KRS;
use App\Models\Akademik\KRSDetail;
use App\Models\Akademik\KrsCollectiveBatch;
use App\Models\Akademik\KrsCollectiveBatchItem;
use App\Models\MasterData\KelasKuliah;
use App\Models\MasterData\Mahasiswa;
use Illuminate\Support\Collection;

class KrsHistoricalPreviewService
{
    public function __construct(
        private readonly KrsHistoricalEligibilityService $eligibilityService,
        private readonly KrsHistoricalKhsGenerationService $khsGenerationService
    ) {
    }

    public function previewBuild(array $payload): Collection
    {
        $buildMode = $payload['build_mode'] ?? 'krs_with_scores';
        $studentPayloads = collect($payload['students_payload'] ?? [])->keyBy('id_mahasiswa');
        $eligibleStudents = $this->eligibilityService
            ->eligibleStudents(array_merge($payload, [
                'id_mahasiswa' => $payload['selected_mahasiswa_ids'] ?? [],
            ]))
            ->keyBy('id');

        return $eligibleStudents->map(function (array $student) use ($payload, $studentPayloads, $buildMode) {
            if ($student['existing_historical_krs']) {
                return $this->previewResult(
                    $student,
                    KrsCollectiveBatchItem::STATUS_SKIPPED,
                    'Mahasiswa sudah memiliki KRS historis pada semester ini',
                    [
                        'action_type' => KrsCollectiveBatch::ACTION_BUILD_HISTORICAL_KRS,
                        'id_krs' => $student['existing_historical_krs']['id'],
                    ]
                );
            }

            if (!$student['is_ready']) {
                return $this->previewResult(
                    $student,
                    KrsCollectiveBatchItem::STATUS_FAILED,
                    $student['message'],
                    ['action_type' => KrsCollectiveBatch::ACTION_BUILD_HISTORICAL_KRS]
                );
            }

            $studentPayload = $studentPayloads->get($student['id']);
            if (!$studentPayload) {
                return $this->previewResult(
                    $student,
                    KrsCollectiveBatchItem::STATUS_FAILED,
                    'Payload kelas dan nilai historis mahasiswa belum disiapkan',
                    ['action_type' => KrsCollectiveBatch::ACTION_BUILD_HISTORICAL_KRS]
                );
            }

            $coursePreview = $this->resolvePackageCoursePayload(
                (string) $payload['id_semester'],
                $student,
                $studentPayload
            );

            if ($coursePreview['status'] !== KrsCollectiveBatchItem::STATUS_READY) {
                return $this->previewResult(
                    $student,
                    $coursePreview['status'],
                    $coursePreview['message'],
                    $coursePreview['meta']
                );
            }

            return $this->previewResult(
                $student,
                KrsCollectiveBatchItem::STATUS_READY,
                $buildMode === 'krs_only'
                    ? 'Mahasiswa siap didaftarkan ke KRS historis tanpa nilai final'
                    : 'Mahasiswa siap dibentuk KRS historis dari paket semester',
                [
                    'action_type' => KrsCollectiveBatch::ACTION_BUILD_HISTORICAL_KRS,
                    'build_mode' => $buildMode,
                    'semester_ke' => (int) $payload['semester_ke'],
                    'total_courses' => count($coursePreview['meta']['courses']),
                    'total_sks' => $coursePreview['meta']['total_sks'],
                    'score_completion' => $buildMode === 'krs_only' ? 'not_required' : 'complete',
                    'courses' => $coursePreview['meta']['courses'],
                ]
            );
        })->values();
    }

    public function previewReopen(array $payload): Collection
    {
        return $this->previewKrsMutation(
            $payload,
            KrsCollectiveBatch::ACTION_REOPEN_HISTORICAL_KRS,
            function (?KRS $krs) {
                if (!$krs) {
                    return [KrsCollectiveBatchItem::STATUS_FAILED, 'KRS historis tidak ditemukan'];
                }

                if ($krs->status_approval !== KRS::STATUS_APPROVED || !$krs->is_locked) {
                    return [KrsCollectiveBatchItem::STATUS_FAILED, 'Hanya KRS historis final yang dapat dibuka ulang'];
                }

                return [KrsCollectiveBatchItem::STATUS_READY, 'KRS historis siap dibuka ulang'];
            }
        );
    }

    public function previewRefinalize(array $payload): Collection
    {
        return $this->previewKrsMutation(
            $payload,
            KrsCollectiveBatch::ACTION_REFINALIZE_HISTORICAL_KRS,
            function (?KRS $krs) {
                if (!$krs) {
                    return [KrsCollectiveBatchItem::STATUS_FAILED, 'KRS historis tidak ditemukan'];
                }

                if ($krs->is_locked || $krs->status_approval !== KRS::STATUS_REVISED) {
                    return [KrsCollectiveBatchItem::STATUS_FAILED, 'KRS historis harus dalam kondisi reopened sebelum difinalisasi ulang'];
                }

                if ($krs->details->isEmpty()) {
                    return [KrsCollectiveBatchItem::STATUS_FAILED, 'KRS historis belum memiliki detail untuk difinalisasi ulang'];
                }

                return [KrsCollectiveBatchItem::STATUS_READY, 'KRS historis siap difinalisasi ulang'];
            }
        );
    }

    public function previewReset(array $payload): Collection
    {
        return $this->previewKrsMutation(
            $payload,
            KrsCollectiveBatch::ACTION_RESET_HISTORICAL_KRS,
            function (?KRS $krs) {
                if (!$krs) {
                    return [KrsCollectiveBatchItem::STATUS_FAILED, 'KRS historis tidak ditemukan'];
                }

                if ($krs->is_locked || $krs->status_approval !== KRS::STATUS_REVISED) {
                    return [KrsCollectiveBatchItem::STATUS_FAILED, 'Reset hanya diizinkan saat KRS historis sudah dibuka ulang'];
                }

                if ($krs->details->isEmpty()) {
                    return [KrsCollectiveBatchItem::STATUS_SKIPPED, 'KRS historis tidak memiliki detail untuk direset'];
                }

                return [KrsCollectiveBatchItem::STATUS_READY, 'Isi KRS historis siap direset'];
            }
        );
    }

    public function previewGenerateKhs(array $payload): Collection
    {
        return $this->khsGenerationService->preview($payload);
    }

    private function previewKrsMutation(array $payload, string $actionType, callable $resolver): Collection
    {
        return $this->loadHistoricalKrsByStudents($payload)->map(function (array $student) use ($resolver, $actionType) {
            [$status, $message] = $resolver($student['krs']);

            return $this->previewResult(
                $student['student'],
                $status,
                $message,
                [
                    'action_type' => $actionType,
                    'id_krs' => $student['krs']?->id,
                    'status_approval' => $student['krs']?->status_approval,
                    'is_locked' => $student['krs']?->is_locked,
                    'total_sks' => $student['krs']?->total_sks,
                ]
            );
        })->values();
    }

    private function loadHistoricalKrsByStudents(array $payload): Collection
    {
        $studentIds = collect($payload['selected_mahasiswa_ids'] ?? $payload['id_mahasiswa'] ?? [])
            ->filter()
            ->values();

        if ($studentIds->isEmpty()) {
            return collect();
        }

        $students = Mahasiswa::query()
            ->with('prodi:id,nama_prodi')
            ->whereIn('id', $studentIds->all())
            ->get()
            ->keyBy('id');

        $krsMap = KRS::query()
            ->with(['details.kelasKuliah.kurikulumMataKuliah.mataKuliah'])
            ->where('id_semester', $payload['id_semester'])
            ->whereIn('id_mahasiswa', $studentIds->all())
            ->get()
            ->keyBy('id_mahasiswa');

        return $studentIds->map(function (string $studentId) use ($students, $krsMap) {
            $student = $students->get($studentId);

            return [
                'student' => [
                    'id' => $student?->id,
                    'nim' => $student?->nim,
                    'nama_mahasiswa' => $student?->nama_mahasiswa,
                    'id_prodi' => $student?->id_prodi,
                    'prodi' => $student?->prodi,
                ],
                'krs' => $krsMap->get($studentId),
            ];
        })->filter(fn(array $item) => !empty($item['student']['id']));
    }

    private function resolvePackageCoursePayload(string $semesterId, array $student, array $studentPayload): array
    {
        $buildMode = $studentPayload['build_mode'] ?? null;
        $courses = collect($studentPayload['courses'] ?? [])->filter(function (array $course) {
            return filled($course['id_kelas_kuliah'] ?? null);
        })->values();

        if ($courses->isEmpty()) {
            return [
                'status' => KrsCollectiveBatchItem::STATUS_FAILED,
                'message' => 'Mahasiswa belum memiliki daftar kelas historis yang valid',
                'meta' => ['action_type' => KrsCollectiveBatch::ACTION_BUILD_HISTORICAL_KRS],
            ];
        }

        $classIds = $courses->pluck('id_kelas_kuliah')->all();
        $classes = KelasKuliah::query()
            ->with('kurikulumMataKuliah.mataKuliah')
            ->whereIn('id', $classIds)
            ->get()
            ->keyBy('id');

        $normalizedCourses = [];
        $totalSks = 0;

        foreach ($courses as $course) {
            $class = $classes->get($course['id_kelas_kuliah']);

            if (!$class) {
                return [
                    'status' => KrsCollectiveBatchItem::STATUS_FAILED,
                    'message' => 'Ada kelas historis yang tidak ditemukan di sistem',
                    'meta' => ['action_type' => KrsCollectiveBatch::ACTION_BUILD_HISTORICAL_KRS],
                ];
            }

            if ((string) $class->id_semester !== $semesterId) {
                return [
                    'status' => KrsCollectiveBatchItem::STATUS_FAILED,
                    'message' => 'Ada kelas historis yang tidak berada pada semester target',
                    'meta' => ['action_type' => KrsCollectiveBatch::ACTION_BUILD_HISTORICAL_KRS],
                ];
            }

            if (!empty($student['id_prodi']) && (string) $class->id_prodi !== (string) $student['id_prodi']) {
                return [
                    'status' => KrsCollectiveBatchItem::STATUS_FAILED,
                    'message' => 'Ada kelas historis yang tidak sesuai dengan prodi mahasiswa',
                    'meta' => ['action_type' => KrsCollectiveBatch::ACTION_BUILD_HISTORICAL_KRS],
                ];
            }

            $sks = (int) ($class->kurikulumMataKuliah?->mataKuliah?->sks ?? 0);
            $totalSks += $sks;

            $normalizedCourse = [
                'id_kelas_kuliah' => $class->id,
                'nama_kelas' => $class->nama_kelas,
                'kode_mk' => $class->kurikulumMataKuliah?->mataKuliah?->kode_mk,
                'nama_mk' => $class->kurikulumMataKuliah?->mataKuliah?->nama_mk,
                'sks' => $sks,
                'catatan' => $course['catatan'] ?? null,
            ];

            $nilaiAkhir = $course['nilai_akhir'] ?? null;
            if ($nilaiAkhir !== null && $nilaiAkhir !== '') {
                $nilaiAkhir = round((float) $nilaiAkhir, 2);
                $grading = KRSDetail::convertNumericScore($nilaiAkhir);
                $normalizedCourse = array_merge($normalizedCourse, [
                    'nilai_akhir' => $nilaiAkhir,
                    'nilai_huruf' => $grading['nilai_huruf'],
                    'mutu' => $grading['bobot_nilai'],
                    'bobot_nilai' => round($sks * (float) $grading['bobot_nilai'], 2),
                    'status' => (float) $grading['bobot_nilai'] >= 2.0
                        ? KRSDetail::STATUS_LULUS
                        : KRSDetail::STATUS_TIDAK_LULUS,
                ]);
            } else {
                $normalizedCourse = array_merge($normalizedCourse, [
                    'nilai_akhir' => null,
                    'nilai_huruf' => null,
                    'mutu' => null,
                    'bobot_nilai' => null,
                    'status' => KRSDetail::STATUS_TERDAFTAR,
                ]);
            }

            $normalizedCourses[] = $normalizedCourse;
        }

        return [
            'status' => KrsCollectiveBatchItem::STATUS_READY,
            'message' => collect($normalizedCourses)->contains(fn(array $course) => $course['nilai_akhir'] === null)
                ? 'Payload kelas historis valid untuk pendaftaran KRS'
                : 'Payload kelas dan nilai historis valid',
            'meta' => [
                'action_type' => KrsCollectiveBatch::ACTION_BUILD_HISTORICAL_KRS,
                'total_sks' => $totalSks,
                'courses' => $normalizedCourses,
            ],
        ];
    }

    private function previewResult(array $student, string $status, string $message, array $meta = []): array
    {
        return [
            'id_mahasiswa' => $student['id'],
            'nim' => $student['nim'],
            'nama_mahasiswa' => $student['nama_mahasiswa'],
            'status' => $status,
            'message' => $message,
            'meta' => $meta,
        ];
    }
}
