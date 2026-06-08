<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Kurikulum extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'kurikulum';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $appends = ['nama_kurikulum', 'nama_kurikulum_induk'];

    protected $fillable = [
        'id_prodi',
        'id_kurikulum_induk',
        'kode_kurikulum',
        'nama_struktur_mk',
        'id_semester',
        'jumlah_sks_wajib',
        'jumlah_sks_pilihan',
        'jumlah_sks_lulus',
    ];

    public function getDisplayNameAttribute(): string
    {
        return (string) $this->nama_struktur_mk;
    }

    public function getNamaKurikulumAttribute(): ?string
    {
        if ($this->relationLoaded('kurikulumInduk') && filled($this->kurikulumInduk?->nama_kurikulum)) {
            return $this->kurikulumInduk?->nama_kurikulum;
        }

        return $this->attributes['nama_struktur_mk'] ?? null;
    }

    public function getNamaKurikulumIndukAttribute(): ?string
    {
        if ($this->relationLoaded('kurikulumInduk')) {
            return $this->kurikulumInduk?->nama_kurikulum;
        }

        return null;
    }

    // Relasi ke Prodi
    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }

    public function kurikulumInduk(): BelongsTo
    {
        return $this->belongsTo(KurikulumInduk::class, 'id_kurikulum_induk');
    }

    public function semesterMulai(): BelongsTo
    {
        return $this->belongsto(Semester::class, 'id_semester');
    }

    public function kurikulumMataKuliah(): HasMany
    {
        return $this->hasMany(KurikulumMataKuliah::class, 'id_kurikulum');
    }

    public function riwayatMahasiswa(): HasMany
    {
        return $this->hasMany(RiwayatKurikulumMahasiswa::class, 'id_kurikulum');
    }

    // Relasi ke Mata Kuliah
    public function mataKuliah(): BelongsToMany
    {
        return $this->belongsToMany(
            MataKuliah::class,
            'kurikulum_mata_kuliah',
            'id_kurikulum',
            'id_mata_kuliah'
        )->withPivot([
            'semester_ke',
            'status_mk',
            'is_wajib',
        ]);
    }
}
