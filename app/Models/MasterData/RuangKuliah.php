<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class RuangKuliah extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ruang_kuliah';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'kode_ruang',
        'nama_ruang',
        'gedung',
        'lantai',
        'kapasitas',
        'is_active',
    ];

    protected $casts = [
        'kapasitas' => 'integer',
        'is_active' => 'boolean',
    ];

    public function jadwalKuliah(): HasMany
    {
        return $this->hasMany(JadwalKuliah::class, 'id_ruang');
    }
}
