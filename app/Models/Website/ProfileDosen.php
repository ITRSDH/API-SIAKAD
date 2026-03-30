<?php

namespace App\Models\Website;

use App\Models\MasterData\Prodi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProfileDosen extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'profile_dosen';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'nama',
        'nidn',
        'foto',
        'status',
        'id_prodi',
        'biografi',
    ];

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }
}
