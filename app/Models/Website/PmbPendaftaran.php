<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PmbPendaftaran extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'pmb_pendaftaran';
    protected $keyType = 'string';
    public $incrementing = false;
    public $primaryKey = 'id';
    
    protected $fillable = [
        'deskripsi',
        'tata_cara',
    ];
}
