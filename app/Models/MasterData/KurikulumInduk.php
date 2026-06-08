<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class KurikulumInduk extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'kurikulum_induk';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_prodi',
        'id_jenis_kurikulum',
        'nama_kurikulum',
        'tahun_kurikulum',
        'kode_kurikulum',
        'is_aktif',
    ];

    protected $appends = [
        'nama_kurikulum',
    ];

    protected $casts = [
        'is_aktif' => 'boolean',
    ];

    public function getNamaKurikulumAttribute(): string
    {
        $prodiLabel = $this->prodi
            ? trim("{$this->prodi->jenjang_pendidikan} {$this->prodi->nama_prodi}")
            : null;
        $tahun = $this->attributes['tahun_kurikulum'] ?? null;
        $jenis = $this->jenisKurikulum?->nama_jenis_kurikulum;

        return collect([$prodiLabel, $tahun, $jenis])
            ->filter(fn($value) => filled($value))
            ->implode(' - ');
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }

    public function jenisKurikulum(): BelongsTo
    {
        return $this->belongsTo(RefJenisKurikulum::class, 'id_jenis_kurikulum');
    }

    public function kurikulumOperasional(): HasMany
    {
        return $this->hasMany(Kurikulum::class, 'id_kurikulum_induk');
    }

    public function mahasiswas(): HasManyThrough
    {
        return $this->hasManyThrough(
            Mahasiswa::class,
            Kurikulum::class,
            'id_kurikulum_induk',
            'id_kurikulum',
            'id',
            'id'
        );
    }
}
