<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kurikulum extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'kurikulum';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_prodi',
        'nama_kurikulum',
        'tahun_kurikulum',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    // Relasi ke Prodi
    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }

    // Relasi ke Mata Kuliah
    public function mataKuliah(): HasMany
    {
        return $this->hasMany(MataKuliah::class, 'id_kurikulum');
    }
}
