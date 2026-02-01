<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Krs extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'krs';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id_mahasiswa',
        'id_semester',
        'tanggal_pengisian',
        'status',
        'tanggal_verifikasi',
        'id_dosen_wali',
        'catatan_verifikasi',
        'jumlah_sks_diambil',
    ];

    protected $casts = [
        'tanggal_pengisian' => 'date',
        'tanggal_verifikasi' => 'date',
    ];

    // Relasi ke Mahasiswa
    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }

    // Relasi ke Semester
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }

    // Relasi ke Dosen Wali
    public function dosenWali(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'id_dosen_wali');
    }

    // Relasi ke KRS Detail
    public function krsDetail(): HasMany
    {
        return $this->hasMany(KrsDetail::class, 'id_krs');
    }
}
