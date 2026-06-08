<?php

namespace App\Services\Krs;

use App\Models\Akademik\KrsCollectiveBatch;
use App\Models\Akademik\KrsCollectiveBatchItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class KrsHistoricalBatchLogService
{
    public function createBatch(array $attributes): KrsCollectiveBatch
    {
        $attributes['context_type'] = $attributes['context_type'] ?? KrsCollectiveBatch::CONTEXT_HISTORICAL_STUDY;
        $attributes['executed_at'] = $attributes['executed_at'] ?? now();

        return KrsCollectiveBatch::create($attributes);
    }

    public function storeItems(KrsCollectiveBatch $batch, array $items): void
    {
        foreach ($items as $item) {
            $batch->items()->create($item);
        }
    }

    public function buildSummary(Collection $results): array
    {
        $normalized = $results->values();

        return [
            'total' => $normalized->count(),
            'ready' => $normalized->where('status', KrsCollectiveBatchItem::STATUS_READY)->count(),
            'executed' => $normalized->where('status', KrsCollectiveBatchItem::STATUS_EXECUTED)->count(),
            'skipped' => $normalized->where('status', KrsCollectiveBatchItem::STATUS_SKIPPED)->count(),
            'failed' => $normalized->where('status', KrsCollectiveBatchItem::STATUS_FAILED)->count(),
        ];
    }

    public function history(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = KrsCollectiveBatch::query()
            ->with(['creator:id,name', 'semester.tahunAkademik', 'items'])
            ->where('context_type', KrsCollectiveBatch::CONTEXT_HISTORICAL_STUDY)
            ->orderByDesc('executed_at')
            ->orderByDesc('created_at');

        if (!empty($filters['id_semester'])) {
            $query->where('id_semester', $filters['id_semester']);
        }

        if (!empty($filters['action_type'])) {
            $query->where('action_type', $filters['action_type']);
        }

        return $query->paginate($perPage);
    }

    public function findBatch(string $id): ?KrsCollectiveBatch
    {
        return KrsCollectiveBatch::query()
            ->with([
                'creator:id,name',
                'semester.tahunAkademik',
                'items.mahasiswa:id,nim,nama_mahasiswa,id_prodi',
                'items.krs',
                'items.khs',
            ])
            ->find($id);
    }
}
