<?php

namespace App\Models\Akademik;

use App\Models\MasterData\KelasKuliah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class KomponenPenilaian extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'komponen_penilaian';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_kelas_kuliah',
        'nama',
        'bobot',
        'urutan',
        'is_active',
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
