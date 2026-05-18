<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JadwalKuliah extends Model
{
    use HasFactory, HasUuids;
    protected $table = 'jadwal_kuliah';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id_kelas_kuliah',
        'id_ruang',
        'hari',
        'jam_mulai',
        'jam_selesai',
    ];

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'id_kelas_kuliah');
    }

    public function ruang(): BelongsTo
    {
        return $this->belongsTo(RuangKuliah::class, 'id_ruang');
    }
}
