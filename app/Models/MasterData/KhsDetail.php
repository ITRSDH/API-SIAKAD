<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KhsDetail extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'khs_detail'; // Singular
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id_khs',
        'id_mk',
        'nilai_huruf',
        'bobot',
        'sks',
        'id_nilai',
    ];

    // Relasi ke KHS
    public function khs(): BelongsTo
    {
        return $this->belongsTo(Khs::class, 'id_khs');
    }

    // Relasi ke Mata Kuliah
    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class, 'id_mk');
    }

    // Relasi ke Nilai
    public function nilai(): BelongsTo
    {
        return $this->belongsTo(Nilai::class, 'id_nilai');
    }
}
