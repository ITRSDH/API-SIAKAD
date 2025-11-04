<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BebanAjarDosen extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'beban_ajar_dosen'; // Singular
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id_dosen',
        'id_kelas_mk',
        'id_semester',
        'jumlah_jam',
    ];

    // Relasi ke Dosen
    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'id_dosen');
    }

    // Relasi ke Kelas MK
    public function kelasMk(): BelongsTo
    {
        return $this->belongsTo(KelasMk::class, 'id_kelas_mk');
    }

    // Relasi ke Semester
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }
}
