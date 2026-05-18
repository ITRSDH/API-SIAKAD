<?php

namespace App\Models\MasterData;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KonversiMataKuliah extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'konversi_mata_kuliah';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    public const STATUS_DIAKUI = 'diakui';
    public const STATUS_WAJIB_ULANG = 'wajib_ulang';
    public const STATUS_PILIHAN_BEBAS = 'pilihan_bebas';

    protected $fillable = [
        'id_kurikulum_asal',
        'id_kurikulum_tujuan',
        'id_mata_kuliah_asal',
        'id_mata_kuliah_tujuan',
        'status_konversi',
        'min_bobot_nilai',
        'catatan',
        'created_by',
    ];

    protected $casts = [
        'min_bobot_nilai' => 'decimal:2',
    ];

    public function kurikulumAsal(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class, 'id_kurikulum_asal');
    }

    public function kurikulumTujuan(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class, 'id_kurikulum_tujuan');
    }

    public function mataKuliahAsal(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class, 'id_mata_kuliah_asal');
    }

    public function mataKuliahTujuan(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class, 'id_mata_kuliah_tujuan');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
