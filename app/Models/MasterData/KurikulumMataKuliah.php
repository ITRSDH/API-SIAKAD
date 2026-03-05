<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KurikulumMataKuliah extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'kurikulum_mata_kuliah';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_kurikulum',
        'id_mata_kuliah',
        'semester_ke',
        'status_mk',
        'is_wajib',
    ];

    // Relasi ke Kurikulum
    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class, 'id_kurikulum');
    }

    // Relasi ke Mata Kuliah
    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class, 'id_mata_kuliah');
    }
}
