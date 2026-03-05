<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndikatorKinerja extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'indikator_kinerja';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_cpl',
        'kode_ik_cpl',
        'deskripsi_ik_cpl_indonesia',
        'deskripsi_ik_cpl_english',
        'kategori_ik_cpl'
    ];

    public function cpl(): BelongsTo
    {
        return $this->belongsTo(Cpl::class, 'id_cpl');
    }
}
