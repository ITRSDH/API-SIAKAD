<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DosenKelasMk extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'dosen_kelas_mk'; // Singular
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id_kelas_mk',
        'id_dosen',
        'peran',
    ];

    // Relasi ke Kelas MK
    public function kelasMk(): BelongsTo
    {
        return $this->belongsTo(KelasMk::class, 'id_kelas_mk');
    }

    // Relasi ke Dosen
    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'id_dosen');
    }
}
