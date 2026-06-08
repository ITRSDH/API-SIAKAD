<?php

namespace App\Models\Akademik;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KhsRevision extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'khs_revisions';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_khs',
        'id_import_batch',
        'revision_number',
        'khs_snapshot',
        'khs_detail_snapshot',
        'created_by',
        'reason',
    ];

    protected $casts = [
        'revision_number' => 'integer',
        'khs_snapshot' => 'array',
        'khs_detail_snapshot' => 'array',
    ];

    public function khs(): BelongsTo
    {
        return $this->belongsTo(KHS::class, 'id_khs');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(KhsImportBatch::class, 'id_import_batch');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
