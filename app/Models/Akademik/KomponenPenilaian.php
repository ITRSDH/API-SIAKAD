<?php

namespace App\Models\Akademik;

use App\Models\MasterData\KelasKuliah;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KomponenPenilaian extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'komponen_penilaian';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id_kelas_kuliah',
        'id_indikator_kinerja_cpl',
        'nama',
        'jenis_evaluasi_dikti',
        'bobot',
        'urutan',
        'is_active',
        'id_komponen_evaluasi_pddikti',
    ];

    protected $casts = [
        'bobot' => 'decimal:2',
        'urutan' => 'integer',
        'is_active' => 'boolean',
    ];

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'id_kelas_kuliah');
    }

    public function nilaiKomponen(): HasMany
    {
        return $this->hasMany(NilaiKomponen::class, 'id_komponen_penilaian');
    }
}
