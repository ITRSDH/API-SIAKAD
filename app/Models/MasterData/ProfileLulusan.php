<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProfileLulusan extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'profile_lulusan';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_prodi',
        'kode_pl',
        'profile_lulusan',
        'deskripsi_profile_lulusan_indonesia',
        'deskripsi_profile_lulusan_english',
        'profesi_lulusan'
    ];

    public function cpl(): BelongsToMany
    {
        return $this->belongsToMany(
            Cpl::class,
            'pl_cpl',
            'id_pl',
            'id_cpl'
        )->withPivot('bobot')
            ->withTimestamps();
    }
}
