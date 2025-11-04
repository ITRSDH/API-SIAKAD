<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KelasMk extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'kelas_mk';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_mk',
        'id_kelas_pararel',
        'id_semester',
        'id_jenis_kelas',
        'kode_kelas_mk',
        'kuota',
    ];

    // Relasi ke Mata Kuliah
    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class, 'id_mk');
    }

    // Relasi ke Kelas Pararel
    public function kelasPararel(): BelongsTo
    {
        return $this->belongsTo(KelasPararel::class, 'id_kelas_pararel');
    }

    // Relasi ke Semester
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }

    // Relasi ke Jenis Kelas
    public function jenisKelas(): BelongsTo
    {
        return $this->belongsTo(JenisKelas::class, 'id_jenis_kelas');
    }

    // Relasi ke Jadwal Kuliah
    public function jadwalKuliah(): HasMany
    {
        return $this->hasMany(JadwalKuliah::class, 'id_kelas_mk');
    }

    // Relasi ke Presensi
    public function presensi(): HasMany
    {
        return $this->hasMany(Presensi::class, 'id_kelas_mk');
    }

    // Relasi ke Nilai
    public function nilai(): HasMany
    {
        return $this->hasMany(Nilai::class, 'id_kelas_mk');
    }

    // Relasi ke KRS Detail
    public function krsDetail(): HasMany
    {
        return $this->hasMany(KrsDetail::class, 'id_kelas_mk');
    }

    // Relasi ke Dosen Kelas MK
    public function dosenKelasMk(): HasMany
    {
        return $this->hasMany(DosenKelasMk::class, 'id_kelas_mk');
    }
}
