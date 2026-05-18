<?php

namespace App\Models\Akademik;

use App\Models\MasterData\Dosen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TugasAkhirPembimbing extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'tugas_akhir_pembimbing';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_tugas_akhir',
        'id_dosen',
        'peran',
        'catatan',
    ];

    public function tugasAkhir(): BelongsTo
    {
        return $this->belongsTo(TugasAkhir::class, 'id_tugas_akhir');
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'id_dosen');
    }
}
