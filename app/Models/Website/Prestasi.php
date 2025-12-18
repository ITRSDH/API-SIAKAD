<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\MasterData\Prodi;

class Prestasi extends Model
{
    use HasFactory, HasUuids;
    
    protected $table = 'prestasi';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id_prodi',
        'nama_mahasiswa',
        'program_studi',
        'judul_prestasi',
        'tingkat',
        'tahun',
        'deskripsi',
        'gambar',
    ];

    protected $casts = [
        'tahun' => 'integer',
    ];

    // Relasi ke Prodi
    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }
}
