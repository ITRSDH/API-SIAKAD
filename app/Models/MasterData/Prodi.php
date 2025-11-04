<?php

namespace App\Models\MasterData;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prodi extends Model
{
    use HasFactory, HasUuids;
    protected $table = 'prodi';
    protected $primaryKey = 'id';
    protected $fillable = [
        'kode_prodi',
        'nama_prodi',
        'id_jenjang_pendidikan',
        'akreditasi',
        'tahun_berdiri',
        'kuota',
        'gelar_lulusan',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    // Relasi ke JenjangPendidikan
    public function jenjang(): BelongsTo
    {
        return $this->belongsTo(JenjangPendidikan::class, 'id_jenjang_pendidikan');
    }

    // Relasi ke Mahasiswa
    public function mahasiswa(): HasMany
    {
        return $this->hasMany(Mahasiswa::class, 'id_prodi');
    }

    // Relasi ke Dosen
    public function dosen(): HasMany
    {
        return $this->hasMany(Dosen::class, 'id_prodi');
    }

    // Relasi ke Kurikulum
    public function kurikulum(): HasMany
    {
        return $this->hasMany(Kurikulum::class, 'id_prodi');
    }

    // Relasi ke Kelas Pararel
    public function kelasPararel(): HasMany
    {
        return $this->hasMany(KelasPararel::class, 'id_prodi');
    }
}
