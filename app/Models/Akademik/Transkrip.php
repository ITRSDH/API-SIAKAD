<?php

namespace App\Models\Akademik;

use App\Models\MasterData\Mahasiswa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transkrip extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'transkrip';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_mahasiswa',
        'total_sks_lulus',
        'ipk',
        'is_final',
        'generated_at',
    ];

    protected $casts = [
        'total_sks_lulus' => 'integer',
        'ipk' => 'decimal:2',
        'is_final' => 'boolean',
        'generated_at' => 'datetime',
    ];

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }

    public function details(): HasMany
    {
        return $this->hasMany(TranskripDetail::class, 'id_transkrip');
    }
}
