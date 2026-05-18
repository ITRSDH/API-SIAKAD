<?php

namespace App\Models\Akademik;

use App\Models\MasterData\KelasKuliah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Remedial extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'remedial';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_krs_detail',
        'id_kelas_kuliah',
        'attempt_ke',
        'tanggal_remedial',
        'nilai_sebelum',
        'nilai_remedial',
        'nilai_final',
        'nilai_huruf_final',
        'bobot_nilai_final',
        'status',
        'catatan',
        'published_at',
    ];

    protected $casts = [
        'attempt_ke' => 'integer',
        'tanggal_remedial' => 'date',
        'nilai_sebelum' => 'decimal:2',
        'nilai_remedial' => 'decimal:2',
        'nilai_final' => 'decimal:2',
        'bobot_nilai_final' => 'decimal:2',
        'published_at' => 'datetime',
    ];

    public function krsDetail(): BelongsTo
    {
        return $this->belongsTo(KRSDetail::class, 'id_krs_detail');
    }

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'id_kelas_kuliah');
    }
}
