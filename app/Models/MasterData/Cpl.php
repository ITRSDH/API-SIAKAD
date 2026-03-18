<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Cpl extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cpl';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_prodi',
        'kode_cpl',
        // 'cpl',
        'deskripsi_cpl_indonesia',
        'deskripsi_cpl_english',
        'kategori_cpl'
    ];

    // PL ↔ CPL
    public function profileLulusan(): BelongsToMany
    {
        return $this->belongsToMany(
            ProfileLulusan::class,
            'pl_cpl',
            'id_cpl',
            'id_profile_lulusan'
        )->withPivot('bobot')
            ->withTimestamps();
    }

    // CPL → IK
    public function indikatorKinerja(): HasMany
    {
        return $this->hasMany(IndikatorKinerja::class, 'id_cpl');
    }

    // CPL ↔ MK
    public function mataKuliah(): BelongsToMany
    {
        return $this->belongsToMany(
            MataKuliah::class,
            'cpl_mk',
            'id_cpl',
            'id_mata_kuliah'
        )->withPivot('bobot')
            ->withTimestamps();
    }
}
