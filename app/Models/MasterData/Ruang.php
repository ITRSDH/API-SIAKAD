<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ruang extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ruang';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'nama_ruang',
        'kapasitas',
        'jenis_ruang',
    ];

    // Relasi ke Jadwal Kuliah
    public function jadwalKuliah(): HasMany
    {
        return $this->hasMany(JadwalKuliah::class, 'id_ruang');
    }
}
