<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JenisPembayaran extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'jenis_pembayaran'; // Singular
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'nama_pembayaran',
        'nominal',
        'keterangan',
    ];

    // Relasi ke Pembayaran Mahasiswa
    public function pembayaran(): HasMany
    {
        return $this->hasMany(PembayaranMahasiswa::class, 'id_jenis_pembayaran');
    }
}
