<?php

namespace App\Models\MasterData;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JenjangPendidikan extends Model
{
    use HasFactory, HasUuids;
    protected $table = 'jenjang_pendidikan';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'kode_jenjang',
        'nama_jenjang',
        'deskripsi',
        'jumlah_semester'
    ];

    // Relasi ke Prodi
    public function prodi()
    {
        return $this->hasMany(Prodi::class, 'id_jenjang_pendidikan');
    }
}
