<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RefJenisKurikulum extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ref_jenis_kurikulum';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'kode_jenis',
        'nama_jenis_kurikulum',
        'is_aktif',
    ];

    protected $casts = [
        'is_aktif' => 'boolean',
    ];

    public function kurikulumInduk(): HasMany
    {
        return $this->hasMany(KurikulumInduk::class, 'id_jenis_kurikulum');
    }
}
