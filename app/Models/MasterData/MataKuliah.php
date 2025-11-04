<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MataKuliah extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'mata_kuliah';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_kurikulum',
        'kode_mk',
        'nama_mk',
        'sks',
        'semester_rekomendasi',
        'jenis',
        'deskripsi',
        'teori',
        'seminar',
        'praktikum',
        'praktek_klinik',
    ];

    // Relasi ke Kurikulum
    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class, 'id_kurikulum');
    }

    // Relasi ke Kelas MK
    public function kelasMk(): HasMany
    {
        return $this->hasMany(KelasMk::class, 'id_mk');
    }

    // Relasi ke KHS Detail
    public function khsDetail(): HasMany
    {
        return $this->hasMany(KhsDetail::class, 'id_mk');
    }
}
