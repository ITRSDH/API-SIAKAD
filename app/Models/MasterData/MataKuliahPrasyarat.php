<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MataKuliahPrasyarat extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'mata_kuliah_prasyarat';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_mata_kuliah',
        'id_mata_kuliah_prasyarat',
        'min_bobot_nilai',
    ];

    protected $casts = [
        'min_bobot_nilai' => 'decimal:2',
    ];

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class, 'id_mata_kuliah');
    }

    public function mataKuliahPrasyarat(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class, 'id_mata_kuliah_prasyarat');
    }
}
