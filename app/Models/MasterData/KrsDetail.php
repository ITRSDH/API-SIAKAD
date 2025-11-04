<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KrsDetail extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'krs_detail'; // Singular
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id_krs',
        'id_kelas_mk',
        'sks_diambil',
    ];

    // Relasi ke KRS
    public function krs(): BelongsTo
    {
        return $this->belongsTo(Krs::class, 'id_krs');
    }

    // Relasi ke Kelas MK
    public function kelasMk(): BelongsTo
    {
        return $this->belongsTo(KelasMk::class, 'id_kelas_mk');
    }
}
