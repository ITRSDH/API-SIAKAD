<?php

namespace App\Models\Akademik;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodeWisuda extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'periode_wisuda';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'nama_periode',
        'tanggal_mulai_pendaftaran',
        'tanggal_selesai_pendaftaran',
        'tanggal_wisuda',
        'lokasi',
        'status',
        'catatan',
    ];

    protected $casts = [
        'tanggal_mulai_pendaftaran' => 'date',
        'tanggal_selesai_pendaftaran' => 'date',
        'tanggal_wisuda' => 'date',
    ];

    public function peserta(): HasMany
    {
        return $this->hasMany(PesertaWisuda::class, 'id_periode_wisuda')
            ->orderByDesc('created_at');
    }
}
