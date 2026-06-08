<?php

namespace App\Models\Akademik;

use App\Models\MasterData\Mahasiswa;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KrsCollectiveBatchItem extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_READY = 'ready';
    public const STATUS_EXECUTED = 'executed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    protected $table = 'krs_collective_batch_items';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_batch',
        'id_mahasiswa',
        'id_krs',
        'id_khs',
        'status',
        'message',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(KrsCollectiveBatch::class, 'id_batch');
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }

    public function krs(): BelongsTo
    {
        return $this->belongsTo(KRS::class, 'id_krs');
    }

    public function khs(): BelongsTo
    {
        return $this->belongsTo(KHS::class, 'id_khs');
    }
}
