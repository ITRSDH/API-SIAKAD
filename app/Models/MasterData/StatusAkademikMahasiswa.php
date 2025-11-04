<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StatusAkademikMahasiswa extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'status_akademik_mahasiswa'; // Singular
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id_mahasiswa',
        'status_baru',
        'tanggal_ubah',
        'keterangan',
    ];

    protected $casts = [
        'tanggal_ubah' => 'date',
    ];

    // Relasi ke Mahasiswa
    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }
}
