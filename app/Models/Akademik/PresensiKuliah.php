<?php

namespace App\Models\Akademik;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresensiKuliah extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_HADIR = 'hadir';

    public const STATUS_IZIN = 'izin';

    public const STATUS_SAKIT = 'sakit';

    public const STATUS_ALPA = 'alpa';

    protected $table = 'presensi_kuliah';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id_pertemuan_kuliah',
        'id_krs_detail',
        'status_kehadiran',
        'catatan',
    ];

    public function pertemuanKuliah(): BelongsTo
    {
        return $this->belongsTo(PertemuanKuliah::class, 'id_pertemuan_kuliah');
    }

    public function krsDetail(): BelongsTo
    {
        return $this->belongsTo(KRSDetail::class, 'id_krs_detail');
    }
}
