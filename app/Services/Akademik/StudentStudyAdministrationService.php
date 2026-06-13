<?php

namespace App\Services\Akademik;

use App\Models\Akademik\KHS;
use App\Models\Akademik\KRS;
use App\Models\Akademik\KhsImportBatch;
use App\Models\Akademik\KrsCollectiveBatch;
use App\Models\Akademik\KrsCollectiveBatchItem;
use App\Models\MasterData\Mahasiswa;
use App\Services\Krs\KrsHistoricalEligibilityService;
use Illuminate\Support\Collection;

class StudentStudyAdministrationService
{
    public function __construct(
        private readonly KrsHistoricalEligibilityService $historicalEligibilityService
    ) {
    }

    public function filters(): array
    {
        return $this->historicalEligibilityService->filters();
    }

    public function summary(array $filters): array
    {
        $studentQuery = Mahasiswa::query()
            ->where(function ($builder) {
                $builder->whereNull('status')
                    ->orWhere('status', '!=', 'nonaktif');
            });

        if (!empty($filters['id_prodi'])) {
            $studentQuery->where('id_prodi', $filters['id_prodi']);
        }

        if (!empty($filters['angkatan'])) {
            $studentQuery->where('angkatan', $filters['angkatan']);
        }

        $students = $studentQuery
            ->select(['id', 'id_prodi', 'angkatan'])
            ->orderBy('angkatan')
            ->get();

        $studentIds = $students->pluck('id')->all();

        if ($studentIds === []) {
            return [
                'summary_cards' => $this->emptySummaryCards(),
                'recent_batches' => [
                    'historical' => [],
                    'imports' => [],
                ],
            ];
        }

        $krsCollection = collect();
        $khsCollection = collect();

        if (!empty($filters['id_semester'])) {
            $krsCollection = KRS::query()
                ->with('details')
                ->where('id_semester', $filters['id_semester'])
                ->whereIn('id_mahasiswa', $studentIds)
                ->get();

            $khsCollection = KHS::query()
                ->where('id_semester', $filters['id_semester'])
                ->whereIn('id_mahasiswa', $studentIds)
                ->get();
        }

        $studentsWithKrs = $krsCollection->pluck('id_mahasiswa')->unique();
        $studentsReadyForKhs = $krsCollection
            ->filter(function (KRS $krs) {
                if (!$krs->is_locked || $krs->status_approval !== KRS::STATUS_APPROVED) {
                    return false;
                }

                $details = $krs->details ?? collect();
                if ($details->isEmpty()) {
                    return false;
                }

                return $details->every(fn($detail) => method_exists($detail, 'isFinalScored') && $detail->isFinalScored());
            })
            ->pluck('id_mahasiswa')
            ->unique();

        $studentsWithKhs = $khsCollection->pluck('id_mahasiswa')->unique();

        $recentHistoricalBatches = KrsCollectiveBatch::query()
            ->with(['creator:id,name', 'semester.tahunAkademik'])
            ->latest('executed_at')
            ->latest('created_at')
            ->limit(3)
            ->get();

        $recentImportBatches = KhsImportBatch::query()
            ->with(['uploader:id,name', 'semester.tahunAkademik'])
            ->latest('processed_at')
            ->latest('created_at')
            ->limit(3)
            ->get();

        return [
            'summary_cards' => [
                [
                    'key' => 'total_mahasiswa',
                    'label' => 'Mahasiswa Terfilter',
                    'value' => $students->count(),
                    'tone' => 'primary',
                    'description' => 'Mahasiswa sesuai konteks filter workspace.',
                ],
                [
                    'key' => 'belum_punya_krs',
                    'label' => 'Belum Punya KRS',
                    'value' => max($students->count() - $studentsWithKrs->count(), 0),
                    'tone' => 'warning',
                    'description' => 'Cocok untuk memulai dari tab KRS Kolektif.',
                ],
                [
                    'key' => 'siap_generate_khs',
                    'label' => 'Siap Generate KHS',
                    'value' => $studentsReadyForKhs->count(),
                    'tone' => 'success',
                    'description' => 'KRS approved dan nilai final sudah lengkap.',
                ],
                [
                    'key' => 'khs_sudah_terbentuk',
                    'label' => 'KHS Sudah Ada',
                    'value' => $studentsWithKhs->count(),
                    'tone' => 'info',
                    'description' => 'Mahasiswa yang sudah memiliki hasil studi pada semester ini.',
                ],
            ],
            'recent_batches' => [
                'historical' => $recentHistoricalBatches->values(),
                'imports' => $recentImportBatches->values(),
            ],
        ];
    }

    public function batchHistory(array $filters = []): array
    {
        $historicalBatches = KrsCollectiveBatch::query()
            ->with(['creator:id,name', 'semester.tahunAkademik'])
            ->when(!empty($filters['id_semester']), fn($query) => $query->where('id_semester', $filters['id_semester']))
            ->get()
            ->toBase()
            ->map(fn(KrsCollectiveBatch $batch) => $this->transformHistoricalBatch($batch));

        $importBatches = KhsImportBatch::query()
            ->with(['uploader:id,name', 'semester.tahunAkademik'])
            ->when(!empty($filters['id_semester']), fn($query) => $query->where('id_semester', $filters['id_semester']))
            ->get()
            ->toBase()
            ->map(fn(KhsImportBatch $batch) => $this->transformImportBatch($batch));

        return $historicalBatches
            ->merge($importBatches)
            ->sortByDesc(fn(array $batch) => $batch['executed_at_sort'] ?? '')
            ->values()
            ->all();
    }

    public function readyForKhs(array $filters = []): array
    {
        if (empty($filters['id_semester'])) {
            return [];
        }

        $studentQuery = Mahasiswa::query()
            ->with('prodi:id,nama_prodi')
            ->where(function ($builder) {
                $builder->whereNull('status')
                    ->orWhere('status', '!=', 'nonaktif');
            });

        if (!empty($filters['id_prodi'])) {
            $studentQuery->where('id_prodi', $filters['id_prodi']);
        }

        if (!empty($filters['angkatan'])) {
            $studentQuery->where('angkatan', $filters['angkatan']);
        }

        $students = $studentQuery
            ->orderBy('angkatan')
            ->orderBy('nim')
            ->get();

        $studentIds = $students->pluck('id')->all();

        $krsCollection = KRS::query()
            ->with([
                'details.kelasKuliah.penilaianKelas',
                'details.kelasKuliah.kurikulumMataKuliah.mataKuliah',
            ])
            ->where('id_semester', $filters['id_semester'])
            ->whereIn('id_mahasiswa', $studentIds)
            ->get()
            ->keyBy('id_mahasiswa');

        $khsCollection = KHS::query()
            ->where('id_semester', $filters['id_semester'])
            ->whereIn('id_mahasiswa', $studentIds)
            ->get()
            ->keyBy('id_mahasiswa');

        return $students->map(function (Mahasiswa $student) use ($krsCollection, $khsCollection) {
            /** @var KRS|null $krs */
            $krs = $krsCollection->get($student->id);
            $existingKhs = $khsCollection->get($student->id);

            if (!$krs) {
                return [
                    'id_mahasiswa' => $student->id,
                    'nim' => $student->nim,
                    'nama_mahasiswa' => $student->nama_mahasiswa,
                    'prodi' => $student->prodi?->nama_prodi,
                    'status' => 'not_ready',
                    'message' => 'KRS semester ini belum ditemukan.',
                    'existing_khs' => $existingKhs?->id,
                ];
            }

            if ($krs->status_approval !== KRS::STATUS_APPROVED || !$krs->is_locked) {
                return [
                    'id_mahasiswa' => $student->id,
                    'nim' => $student->nim,
                    'nama_mahasiswa' => $student->nama_mahasiswa,
                    'prodi' => $student->prodi?->nama_prodi,
                    'status' => 'not_ready',
                    'message' => 'KRS belum approved atau belum dikunci.',
                    'existing_khs' => $existingKhs?->id,
                ];
            }

            $details = $krs->details ?? collect();
            $pendingCount = $details->filter(fn($detail) => $detail->status === \App\Models\Akademik\KRSDetail::STATUS_TERDAFTAR)->count();
            $finalCount = $details->filter(fn($detail) => method_exists($detail, 'isFinalScored') && $detail->isFinalScored())->count();

            return [
                'id_mahasiswa' => $student->id,
                'nim' => $student->nim,
                'nama_mahasiswa' => $student->nama_mahasiswa,
                'prodi' => $student->prodi?->nama_prodi,
                'status' => $pendingCount === 0 && $finalCount > 0 ? 'ready' : 'not_ready',
                'message' => $pendingCount === 0 && $finalCount > 0
                    ? 'Siap dipreview dan digenerate.'
                    : 'Masih ada nilai atau hasil studi yang belum final.',
                'existing_khs' => $existingKhs?->id,
                'existing_khs_is_final' => (bool) ($existingKhs?->is_final ?? false),
                'total_detail' => $details->count(),
                'final_detail' => $finalCount,
                'pending_detail' => $pendingCount,
            ];
        })->values()->all();
    }

    public function findBatch(string $source, string $id): ?array
    {
        if ($source === 'historical') {
            $batch = KrsCollectiveBatch::query()
                ->with([
                    'creator:id,name',
                    'semester.tahunAkademik',
                    'items.mahasiswa:id,nim,nama_mahasiswa',
                ])
                ->find($id);

            return $batch ? $this->transformHistoricalBatchDetail($batch) : null;
        }

        if ($source === 'import') {
            $batch = KhsImportBatch::query()
                ->with([
                    'uploader:id,name',
                    'semester.tahunAkademik',
                    'errors',
                    'revisions.creator:id,name',
                ])
                ->find($id);

            return $batch ? $this->transformImportBatchDetail($batch) : null;
        }

        return null;
    }

    private function emptySummaryCards(): array
    {
        return [
            [
                'key' => 'total_mahasiswa',
                'label' => 'Mahasiswa Terfilter',
                'value' => 0,
                'tone' => 'primary',
                'description' => 'Mahasiswa sesuai konteks filter workspace.',
            ],
            [
                'key' => 'belum_punya_krs',
                'label' => 'Belum Punya KRS',
                'value' => 0,
                'tone' => 'warning',
                'description' => 'Cocok untuk memulai dari tab KRS Kolektif.',
            ],
            [
                'key' => 'siap_generate_khs',
                'label' => 'Siap Generate KHS',
                'value' => 0,
                'tone' => 'success',
                'description' => 'KRS approved dan nilai final sudah lengkap.',
            ],
            [
                'key' => 'khs_sudah_terbentuk',
                'label' => 'KHS Sudah Ada',
                'value' => 0,
                'tone' => 'info',
                'description' => 'Mahasiswa yang sudah memiliki hasil studi pada semester ini.',
            ],
        ];
    }

    private function transformHistoricalBatch(KrsCollectiveBatch $batch): array
    {
        $summary = $batch->summary ?? [];

        return [
            'source' => 'historical',
            'id' => $batch->id,
            'title' => $this->resolveHistoricalActionLabel($batch->action_type),
            'subtitle' => 'Riwayat Studi Historis',
            'status' => $this->resolveBatchStatusFromSummary($summary),
            'operator_name' => $batch->creator?->name,
            'semester' => $batch->semester,
            'action_type' => $batch->action_type,
            'summary' => [
                'total' => (int) ($summary['total'] ?? 0),
                'executed' => (int) ($summary['executed'] ?? 0),
                'skipped' => (int) ($summary['skipped'] ?? 0),
                'failed' => (int) ($summary['failed'] ?? 0),
            ],
            'executed_at' => $batch->executed_at ?? $batch->created_at,
            'executed_at_sort' => optional($batch->executed_at ?? $batch->created_at)?->toISOString(),
        ];
    }

    private function transformImportBatch(KhsImportBatch $batch): array
    {
        $summary = $batch->summary ?? [];

        return [
            'source' => 'import',
            'id' => $batch->id,
            'title' => $batch->file_name ?: 'Batch Import Nilai',
            'subtitle' => 'Import Nilai KHS',
            'status' => (string) ($batch->status ?? 'uploaded'),
            'operator_name' => $batch->uploader?->name,
            'semester' => $batch->semester,
            'action_type' => 'import_nilai_khs',
            'summary' => [
                'total' => (int) ($batch->total_rows ?? 0),
                'executed' => (int) ($batch->total_success ?? 0),
                'skipped' => (int) ($summary['total_warning'] ?? 0),
                'failed' => (int) ($batch->total_failed ?? 0),
            ],
            'executed_at' => $batch->processed_at ?? $batch->created_at,
            'executed_at_sort' => optional($batch->processed_at ?? $batch->created_at)?->toISOString(),
        ];
    }

    private function transformHistoricalBatchDetail(KrsCollectiveBatch $batch): array
    {
        $base = $this->transformHistoricalBatch($batch);

        $base['notes'] = $batch->notes;
        $base['filters'] = $batch->filters ?? [];
        $base['payload'] = $batch->payload ?? [];
        $base['items'] = $batch->items
            ->toBase()
            ->map(function (KrsCollectiveBatchItem $item) {
                return [
                    'id' => $item->id,
                    'status' => $item->status,
                    'message' => $item->message,
                    'mahasiswa' => [
                        'id' => $item->mahasiswa?->id,
                        'nim' => $item->mahasiswa?->nim,
                        'nama_mahasiswa' => $item->mahasiswa?->nama_mahasiswa,
                    ],
                    'meta' => $item->meta ?? [],
                ];
            })
            ->values()
            ->all();

        return $base;
    }

    private function transformImportBatchDetail(KhsImportBatch $batch): array
    {
        $base = $this->transformImportBatch($batch);

        $base['file_name'] = $batch->file_name;
        $base['notes'] = null;
        $base['filters'] = [
            'id_semester' => $batch->id_semester,
        ];
        $base['payload'] = [];
        $base['errors'] = $batch->errors->toBase()->map(function ($error) {
            return [
                'row_number' => $error->row_number,
                'nim' => $error->nim,
                'kode_mk' => $error->kode_mk,
                'error_type' => $error->error_type,
                'message' => $error->message,
            ];
        })->values()->all();
        $base['revisions'] = $batch->revisions->toBase()->map(function ($revision) {
            return [
                'revision_number' => $revision->revision_number,
                'reason' => $revision->reason,
                'creator_name' => $revision->creator?->name,
                'created_at' => $revision->created_at,
            ];
        })->values()->all();

        return $base;
    }

    private function resolveHistoricalActionLabel(?string $actionType): string
    {
        return match ($actionType) {
            KrsCollectiveBatch::ACTION_BUILD_HISTORICAL_KRS => 'Bentuk KRS Historis',
            KrsCollectiveBatch::ACTION_REOPEN_HISTORICAL_KRS => 'Buka Ulang Riwayat',
            KrsCollectiveBatch::ACTION_REFINALIZE_HISTORICAL_KRS => 'Finalisasi Ulang Riwayat',
            KrsCollectiveBatch::ACTION_RESET_HISTORICAL_KRS => 'Reset Isi Riwayat',
            KrsCollectiveBatch::ACTION_GENERATE_KHS => 'Generate KHS Historis',
            default => ucfirst(str_replace('_', ' ', (string) $actionType)),
        };
    }

    private function resolveBatchStatusFromSummary(array $summary): string
    {
        $executed = (int) ($summary['executed'] ?? 0);
        $failed = (int) ($summary['failed'] ?? 0);
        $skipped = (int) ($summary['skipped'] ?? 0);

        if ($executed > 0 && $failed === 0) {
            return 'processed';
        }

        if ($executed > 0 && $failed > 0) {
            return 'partial';
        }

        if ($failed > 0 && $executed === 0) {
            return 'failed';
        }

        if ($skipped > 0) {
            return 'previewed';
        }

        return 'uploaded';
    }
}
