<?php

namespace App\Models\Akademik;

use App\Models\MasterData\Semester;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KhsImportBatch extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'khs_import_batches';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_semester',
        'uploaded_by',
        'file_name',
        'file_path',
        'status',
        'total_rows',
        'total_success',
        'total_failed',
        'summary',
        'processed_at',
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'total_success' => 'integer',
        'total_failed' => 'integer',
        'summary' => 'array',
        'processed_at' => 'datetime',
    ];

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function errors(): HasMany
    {
        return $this->hasMany(KhsImportError::class, 'id_import_batch');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(KhsRevision::class, 'id_import_batch');
    }

    public function details(): HasMany
    {
        return $this->hasMany(KHSDetail::class, 'id_import_batch');
    }

    public function krsDetails(): HasMany
    {
        return $this->hasMany(KRSDetail::class, 'id_import_batch');
    }
}
