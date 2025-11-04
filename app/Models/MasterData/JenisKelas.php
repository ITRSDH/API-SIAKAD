<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisKelas extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'jenis_kelas';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'nama_kelas',
        'deskripsi',
    ];

    // Relasi ke Kelas MK
    public function kelasMk(): HasMany
    {
        return $this->hasMany(KelasMk::class, 'id_jenis_kelas');
    }
}
