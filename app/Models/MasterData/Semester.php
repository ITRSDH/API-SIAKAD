<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Semester extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'semester';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_tahun_akademik',
        'nama_semester',
        'kode_semester',
        'tanggal_mulai',
        'tanggal_selesai',
        'status',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
    ];

    // Relasi ke Tahun Akademik
    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class, 'id_tahun_akademik');
    }

    // Relasi ke Jadwal Kuliah
    public function jadwalKuliah(): HasMany
    {
        return $this->hasMany(JadwalKuliah::class, 'id_semester');
    }

    // Relasi ke Nilai
    public function nilai(): HasMany
    {
        return $this->hasMany(Nilai::class, 'id_semester');
    }

    // Relasi ke KRS
    public function krs(): HasMany
    {
        return $this->hasMany(Krs::class, 'id_semester');
    }

    // Relasi ke KHS
    public function khs(): HasMany
    {
        return $this->hasMany(Khs::class, 'id_semester');
    }
}
