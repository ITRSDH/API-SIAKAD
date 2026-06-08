<?php

namespace App\Services\Khs;

use App\Models\Akademik\KHS;
use App\Models\Akademik\KhsRevision;

class KhsRevisionService
{
    public function buildSnapshot(array $khsPayload, array $detailPayload): array
    {
        return [
            'khs_snapshot' => $khsPayload,
            'khs_detail_snapshot' => $detailPayload,
        ];
    }

    public function createSnapshot(KHS $khs, ?string $importBatchId = null, ?string $createdBy = null, ?string $reason = null): KhsRevision
    {
        $nextRevisionNumber = ((int) $khs->revisions()->max('revision_number')) + 1;
        $details = $khs->details()->orderBy('created_at')->get()->map(function ($detail) {
            return $detail->toArray();
        })->all();

        return KhsRevision::create([
            'id_khs' => $khs->id,
            'id_import_batch' => $importBatchId,
            'revision_number' => $nextRevisionNumber,
            'khs_snapshot' => $khs->toArray(),
            'khs_detail_snapshot' => $details,
            'created_by' => $createdBy,
            'reason' => $reason,
        ]);
    }
}
