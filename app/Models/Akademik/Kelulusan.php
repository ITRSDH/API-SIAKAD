<?php

namespace App\Models\Akademik;

use App\Models\MasterData\Mahasiswa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Kelulusan extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'kelulusan';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_mahasiswa',
        'id_yudisium',
        'tanggal_lulus',
        'nomor_sk',
        'nomor_ijazah',
        'status',
        'catatan',
        'generated_at',
    ];

    protected $casts = [
        'tanggal_lulus' => 'date',
        'generated_at' => 'datetime',
    ];

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }

    public function yudisium(): BelongsTo
    {
        return $this->belongsTo(Yudisium::class, 'id_yudisium');
    }

    public function pesertaWisuda(): HasMany
    {
        return $this->hasMany(PesertaWisuda::class, 'id_kelulusan');
    }
}
