<?php

namespace App\Models\Akademik;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NilaiKomponen extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'nilai_komponen';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_komponen_penilaian',
        'id_krs_detail',
        'nilai',
        'catatan',
    ];

    protected $casts = [
        'nilai' => 'decimal:2',
    ];

    public function komponenPenilaian(): BelongsTo
    {
        return $this->belongsTo(KomponenPenilaian::class, 'id_komponen_penilaian');
    }

    public function krsDetail(): BelongsTo
    {
        return $this->belongsTo(KRSDetail::class, 'id_krs_detail');
    }
}
