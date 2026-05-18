<?php

namespace App\Models\Akademik;

use App\Models\MasterData\KelasKuliah;
use App\Models\MasterData\MataKuliah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class KHSDetail extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'khs_detail';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_khs',
        'id_krs_detail',
        'id_kelas_kuliah',
        'id_mata_kuliah',
        'kode_mk',
        'nama_mk',
        'sks',
        'nilai_akhir',
        'nilai_huruf',
        'bobot_nilai',
        'status',
    ];

    protected $casts = [
        'sks' => 'integer',
        'nilai_akhir' => 'decimal:2',
        'bobot_nilai' => 'decimal:2',
    ];

    public function khs(): BelongsTo
    {
        return $this->belongsTo(KHS::class, 'id_khs');
    }

    public function krsDetail(): BelongsTo
    {
        return $this->belongsTo(KRSDetail::class, 'id_krs_detail');
    }

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'id_kelas_kuliah');
    }

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class, 'id_mata_kuliah');
    }
}
