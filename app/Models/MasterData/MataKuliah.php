<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MataKuliah extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'mata_kuliah';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_prodi',
        'kode_mk',
        'nama_mk',
        'sks',
        'sks_tatap_muka',
        'sks_praktikum',
        'sks_praktek_lapangan',
        'sks_simulasi',
        'jenis_mk',
        'kelompok_mk',
    ];

    // Relasi ke Prodi
    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }

    public function kurikulum(): BelongsToMany
    {
        return $this->belongsToMany(
            Kurikulum::class,
            'kurikulum_mata_kuliah',
            'id_mata_kuliah',
            'id_kurikulum'
        )->withPivot([
            'semester_ke',
            'status_mk',
            'is_wajib',
        ]);
    }
}
