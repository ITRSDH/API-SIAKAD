<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SertifikatAkreditasiFoto extends Model
{
    use HasUuids;

    protected $table = 'sertifikat_akreditasi_foto';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'sertifikat_akreditasi_id',
        'foto',
    ];

    public function sertifikat()
    {
        return $this->belongsTo(
            SertifikatAkreditasi::class,
            'sertifikat_akreditasi_id'
        );
    }
}
