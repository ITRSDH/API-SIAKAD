<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SertifikatAkreditasi extends Model
{
    use HasUuids;

    protected $table = 'sertifikat_akreditasi';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'nama',
        'deskripsi',
        'foto_sertifikat',
    ];
}
