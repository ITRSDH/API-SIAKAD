<?php

namespace App\Models\Akademik;

use App\Models\MasterData\Kurikulum;
use App\Models\MasterData\Mahasiswa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Yudisium extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'yudisium';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_mahasiswa',
        'id_transkrip',
        'id_kurikulum',
        'target_sks_lulus',
        'total_sks_lulus',
        'ipk',
        'status',
        'predikat_lulus',
        'tanggal_yudisium',
        'catatan',
        'generated_at',
    ];

    protected $casts = [
        'target_sks_lulus' => 'integer',
        'total_sks_lulus' => 'integer',
        'ipk' => 'decimal:2',
        'tanggal_yudisium' => 'date',
        'generated_at' => 'datetime',
    ];

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }

    public function transkrip(): BelongsTo
    {
        return $this->belongsTo(Transkrip::class, 'id_transkrip');
    }

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class, 'id_kurikulum');
    }
}
