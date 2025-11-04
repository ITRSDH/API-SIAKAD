<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class KelasMahasiswa extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'kelas_mahasiswa'; // Singular
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id_kelas_pararel',
        'id_mahasiswa',
    ];

    // Relasi ke Kelas Pararel
    public function kelasPararel(): BelongsTo
    {
        return $this->belongsTo(KelasPararel::class, 'id_kelas_pararel');
    }

    // Relasi ke Mahasiswa
    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }
}
