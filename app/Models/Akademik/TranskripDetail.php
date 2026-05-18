<?php

namespace App\Models\Akademik;

use App\Models\MasterData\MataKuliah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TranskripDetail extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'transkrip_detail';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_transkrip',
        'id_khs_detail',
        'id_krs_detail',
        'id_mata_kuliah',
        'kode_mk',
        'nama_mk',
        'sks',
        'nilai_akhir',
        'nilai_huruf',
        'bobot_nilai',
        'status',
        'semester_label',
    ];

    protected $casts = [
        'sks' => 'integer',
        'nilai_akhir' => 'decimal:2',
        'bobot_nilai' => 'decimal:2',
    ];

    public function transkrip(): BelongsTo
    {
        return $this->belongsTo(Transkrip::class, 'id_transkrip');
    }

    public function khsDetail(): BelongsTo
    {
        return $this->belongsTo(KHSDetail::class, 'id_khs_detail');
    }

    public function krsDetail(): BelongsTo
    {
        return $this->belongsTo(KRSDetail::class, 'id_krs_detail');
    }

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class, 'id_mata_kuliah');
    }
}
