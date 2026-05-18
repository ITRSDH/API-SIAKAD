<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DosenPengajarKelas extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'dosen_pengajar_kelas';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id_kelas_kuliah',
        'id_registrasi_dosen',
        'sks_substansi_total',
        'rencana_tatap_muka',
        'realisasi_tatap_muka',
        'urutan',
        'id_jenis_evaluasi'
    ];

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'id_registrasi_dosen');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'id_kelas_kuliah');
    }
}
