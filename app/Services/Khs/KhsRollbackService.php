<?php

namespace App\Services\Khs;

use App\Models\Akademik\KHS;
use App\Models\Akademik\KHSDetail;
use App\Models\Akademik\KRSDetail;
use App\Models\Akademik\KhsImportBatch;
use App\Models\Akademik\KhsRevision;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KhsRollbackService
{
    private ?bool $hasKeteranganColumn = null;

    public function rollback(string $batchId, ?string $rolledBackBy = null): array
    {
        $batch = KhsImportBatch::query()->find($batchId);

        if (!$batch) {
            return [
                'rolled_back' => false,
                'message' => 'Batch import KHS tidak ditemukan.',
                'batch_id' => $batchId,
            ];
        }

        if ($batch->status !== 'processed') {
            return [
                'rolled_back' => false,
                'message' => 'Rollback hanya dapat dilakukan untuk batch yang sudah diproses.',
                'batch_id' => $batchId,
            ];
        }

        $processedKhsIds = collect($batch->summary['processed_khs_ids'] ?? [])->filter()->values();
        $processedKrsDetailIds = collect($batch->summary['processed_krs_detail_ids'] ?? [])->filter()->values();
        $conflictedKhsIds = $this->findConflictedKhsIds($processedKhsIds, $batch);
        $conflictedKrsDetailIds = $this->findConflictedKrsDetailIds($processedKrsDetailIds, $batch);

        if ($conflictedKhsIds->isNotEmpty() || $conflictedKrsDetailIds->isNotEmpty()) {
            return [
                'rolled_back' => false,
                'message' => 'Rollback diblokir karena sebagian data hasil import sudah diproses ulang oleh batch yang lebih baru.',
                'batch_id' => $batchId,
                'conflicted_khs_ids' => $conflictedKhsIds->values()->all(),
                'conflicted_krs_detail_ids' => $conflictedKrsDetailIds->values()->all(),
            ];
        }

        $rolledBackIds = [];
        $rolledBackKrsDetailIds = [];

        DB::transaction(function () use ($batch, $processedKhsIds, &$rolledBackIds, &$rolledBackKrsDetailIds, $rolledBackBy) {
            $this->restoreKrsDetailSnapshots($batch, $rolledBackKrsDetailIds);

            foreach ($processedKhsIds as $khsId) {
                /** @var KHS|null $khs */
                $khs = KHS::query()->with('details')->find($khsId);

                if (!$khs) {
                    continue;
                }

                /** @var KhsRevision|null $revision */
                $revision = KhsRevision::query()
                    ->where('id_khs', $khs->id)
                    ->where('id_import_batch', $batch->id)
                    ->latest('created_at')
                    ->first();

                if ($revision) {
                    $snapshot = $revision->khs_snapshot ?? [];
                    $detailSnapshot = collect($revision->khs_detail_snapshot ?? []);

                    $khs->update($this->withOptionalKeterangan([
                        'total_sks_diambil' => $snapshot['total_sks_diambil'] ?? 0,
                        'total_sks_lulus' => $snapshot['total_sks_lulus'] ?? 0,
                        'ips' => $snapshot['ips'] ?? 0,
                        'ipk' => $snapshot['ipk'] ?? 0,
                        'keterangan' => $snapshot['keterangan'] ?? null,
                        'is_final' => $snapshot['is_final'] ?? false,
                        'updated_by' => $snapshot['updated_by'] ?? null,
                        'finalized_by' => $snapshot['finalized_by'] ?? null,
                        'finalized_at' => $snapshot['finalized_at'] ?? null,
                        'generated_at' => $snapshot['generated_at'] ?? $khs->generated_at,
                    ]));

                    $khs->details()->delete();

                    foreach ($detailSnapshot as $detail) {
                        KHSDetail::create([
                            'id_khs' => $khs->id,
                            'id_krs_detail' => $detail['id_krs_detail'] ?? null,
                            'id_kelas_kuliah' => $detail['id_kelas_kuliah'] ?? null,
                            'id_mata_kuliah' => $detail['id_mata_kuliah'] ?? null,
                            'id_import_batch' => $detail['id_import_batch'] ?? null,
                            'kode_mk' => $detail['kode_mk'] ?? null,
                            'nama_mk' => $detail['nama_mk'] ?? null,
                            'sks' => $detail['sks'] ?? 0,
                            'nilai_akhir' => $detail['nilai_akhir'] ?? null,
                            'nilai_huruf' => $detail['nilai_huruf'] ?? null,
                            'bobot_nilai' => $detail['bobot_nilai'] ?? null,
                            'mutu' => $detail['mutu'] ?? null,
                            'status' => $detail['status'] ?? 'terdaftar',
                        ]);
                    }
                } else {
                    $khs->details()->delete();
                    $khs->delete();
                }

                $rolledBackIds[] = $khsId;
            }

            $batch->update([
                'status' => 'rolled_back',
                'summary' => array_merge($batch->summary ?? [], [
                    'rolled_back_khs_ids' => $rolledBackIds,
                    'rolled_back_krs_detail_ids' => $rolledBackKrsDetailIds,
                    'rolled_back_by' => $rolledBackBy,
                    'rolled_back_at' => now()->toDateTimeString(),
                ]),
            ]);
        });

        return [
            'rolled_back' => true,
            'message' => 'Rollback import KHS berhasil diproses.',
            'batch_id' => $batchId,
            'rolled_back_khs_ids' => $rolledBackIds,
            'rolled_back_krs_detail_ids' => $rolledBackKrsDetailIds,
        ];
    }

    private function restoreKrsDetailSnapshots(KhsImportBatch $batch, array &$rolledBackKrsDetailIds): void
    {
        $snapshots = collect($batch->summary['krs_detail_snapshots'] ?? [])
            ->keyBy('id');

        if ($snapshots->isEmpty()) {
            return;
        }

        $currentDetails = KRSDetail::query()
            ->whereIn('id', $snapshots->keys()->all())
            ->get()
            ->keyBy('id');

        foreach ($snapshots as $detailId => $snapshot) {
            $detail = $currentDetails->get($detailId);
            if (!$detail) {
                continue;
            }

            $detail->update([
                'id_mata_kuliah' => $snapshot['id_mata_kuliah'] ?? null,
                'id_import_batch' => $snapshot['id_import_batch'] ?? null,
                'status' => $snapshot['status'] ?? KRSDetail::STATUS_TERDAFTAR,
                'catatan' => $snapshot['catatan'] ?? null,
                'nilai_akhir' => $snapshot['nilai_akhir'] ?? null,
                'nilai_huruf' => $snapshot['nilai_huruf'] ?? null,
                'bobot_nilai' => $snapshot['bobot_nilai'] ?? null,
                'mutu' => $snapshot['mutu'] ?? null,
            ]);

            $rolledBackKrsDetailIds[] = $detailId;
        }
    }

    private function findConflictedKhsIds(Collection $processedKhsIds, KhsImportBatch $batch): Collection
    {
        return $processedKhsIds->filter(function (string $khsId) use ($batch) {
            return KHSDetail::query()
                ->where('id_khs', $khsId)
                ->whereNotNull('id_import_batch')
                ->where('id_import_batch', '!=', $batch->id)
                ->exists();
        });
    }

    private function findConflictedKrsDetailIds(Collection $processedKrsDetailIds, KhsImportBatch $batch): Collection
    {
        return $processedKrsDetailIds->filter(function (string $detailId) use ($batch) {
            return KRSDetail::query()
                ->where('id', $detailId)
                ->whereNotNull('id_import_batch')
                ->where('id_import_batch', '!=', $batch->id)
                ->exists();
        });
    }

    private function withOptionalKeterangan(array $attributes): array
    {
        if ($this->hasKeteranganColumn()) {
            return $attributes;
        }

        unset($attributes['keterangan']);

        return $attributes;
    }

    private function hasKeteranganColumn(): bool
    {
        return $this->hasKeteranganColumn ??= Schema::hasColumn('khs', 'keterangan');
    }
}
