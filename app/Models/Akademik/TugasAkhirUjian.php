<?php

namespace App\Models\Akademik;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TugasAkhirUjian extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'tugas_akhir_ujian';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_tugas_akhir',
        'jenis_ujian',
        'tanggal_ujian',
        'nilai_ujian',
        'keputusan',
        'catatan',
    ];

    protected $casts = [
        'tanggal_ujian' => 'date',
        'nilai_ujian' => 'decimal:2',
    ];

    public function tugasAkhir(): BelongsTo
    {
        return $this->belongsTo(TugasAkhir::class, 'id_tugas_akhir');
    }
}
