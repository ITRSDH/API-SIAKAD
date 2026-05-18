<?php

namespace App\Models\Akademik;

use App\Models\MasterData\Mahasiswa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PesertaWisuda extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'peserta_wisuda';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_periode_wisuda',
        'id_mahasiswa',
        'id_kelulusan',
        'tanggal_daftar',
        'status',
        'status_validasi_administrasi',
        'nomor_peserta',
        'catatan',
    ];

    protected $casts = [
        'tanggal_daftar' => 'date',
    ];

    public function periodeWisuda(): BelongsTo
    {
        return $this->belongsTo(PeriodeWisuda::class, 'id_periode_wisuda');
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }

    public function kelulusan(): BelongsTo
    {
        return $this->belongsTo(Kelulusan::class, 'id_kelulusan');
    }
}
