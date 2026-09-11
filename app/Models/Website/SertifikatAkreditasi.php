<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

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
    ];

    public function fotos()
    {
        return $this->hasMany(
            SertifikatAkreditasiFoto::class,
            'sertifikat_akreditasi_id'
        );
    }
}
