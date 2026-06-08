<?php

namespace App\Models\Akademik;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KhsImportError extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'khs_import_errors';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_import_batch',
        'row_number',
        'nim',
        'kode_mk',
        'error_type',
        'message',
        'payload',
    ];

    protected $casts = [
        'row_number' => 'integer',
        'payload' => 'array',
    ];

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(KhsImportBatch::class, 'id_import_batch');
    }
}
