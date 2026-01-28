<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalKuliah extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'jadwal_kuliah'; // Singular
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id_dosen',
        'id_kelas_mk',
        'id_ruang',
        'id_semester',
        'hari',
        'jam_mulai',
        'jam_selesai',
    ];

    protected $casts = [
        'jam_mulai' => 'datetime:H:i',
        'jam_selesai' => 'datetime:H:i',
    ];

    // Relasi ke Kelas MK
    public function kelasMk(): BelongsTo
    {
        return $this->belongsTo(KelasMk::class, 'id_kelas_mk');
    }

    // Relasi ke Dosen
    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'id_dosen');
    }

    // Relasi ke Ruang
    public function ruang(): BelongsTo
    {
        return $this->belongsTo(Ruang::class, 'id_ruang');
    }

    // Relasi ke Semester
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }
}
