<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KelasPararel extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'kelas_pararel';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_prodi',
        'nama_kelas',
        'angkatan',
    ];

    // Relasi ke Prodi
    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }

    // Relasi ke Kelas MK
    public function kelasMk(): HasMany
    {
        return $this->hasMany(KelasMk::class, 'id_kelas_pararel');
    }

    // Relasi ke Kelas Mahasiswa
    public function kelasMahasiswa(): HasMany
    {
        return $this->hasMany(KelasMahasiswa::class, 'id_kelas_pararel');
    }
}
