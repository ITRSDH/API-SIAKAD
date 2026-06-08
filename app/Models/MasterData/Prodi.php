<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prodi extends Model
{
    use HasFactory, HasUuids;
    protected $table = 'prodi';
    protected $primaryKey = 'id';
    protected $fillable = [
        'kode_prodi',
        'nama_prodi',
        'jenjang_pendidikan',
        'akreditasi',
        'tahun_berdiri',
        'gelar_lulusan',
        'id_kaprodi',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    // Relasi ke Dosen (sebagai Kaprodi)
    public function kaprodi(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'id_kaprodi');
    }

    // Relasi ke Mahasiswa
    public function mahasiswa(): HasMany
    {
        return $this->hasMany(Mahasiswa::class, 'id_prodi');
    }

    // Relasi ke Dosen
    public function dosen(): HasMany
    {
        return $this->hasMany(Dosen::class, 'id_prodi');
    }

    // Relasi ke Kurikulum
    public function kurikulum(): HasMany
    {
        return $this->hasMany(Kurikulum::class, 'id_prodi');
    }

    public function kurikulumInduk(): HasMany
    {
        return $this->hasMany(KurikulumInduk::class, 'id_prodi');
    }

    // Relasi ke matakuliah
    public function matakuliah(): HasMany
    {
        return $this->hasMany(MataKuliah::class, 'id_prodi');
    }

    // Relasi ke Prestasi
    public function prestasi(): HasMany
    {
        return $this->hasMany(\App\Models\Website\Prestasi::class, 'id_prodi');
    }

    // Relasi ke ProfileDosen
    public function profileDosen(): HasMany
    {
        return $this->hasMany(\App\Models\Website\ProfileDosen::class, 'id_prodi');
    }   
}
