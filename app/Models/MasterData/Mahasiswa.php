<?php

namespace App\Models\MasterData;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Mahasiswa extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'mahasiswa';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id_prodi',
        'id_dosen',
        'user_id',
        'nim',
        'nik',
        'nama_mahasiswa',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'tanggal_masuk',
        'alamat',
        'agama',
        'status',
        'angkatan',
    ];

    // Relasi ke Prodi
    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }

    // Relasi ke Dosen (Wali)
    public function dosenWali(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'id_dosen');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
