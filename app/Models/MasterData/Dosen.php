<?php

namespace App\Models\MasterData;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Dosen extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'dosen'; // Singular
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id_prodi',
        'user_id',
        'nidn',
        'nup',
        'nama_dosen',
        'jenis_kelamin',
        'tanggal_lahir',
        'alamat',
        'no_hp',
        'email',
        'jabatan_akademik',
        'pangkat_golongan',
        'status_aktif',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'status_aktif' => 'boolean',
    ];

    // Relasi ke Prodi
    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }

    // Relasi ke Jadwal Kuliah
    public function jadwalKuliah(): HasMany
    {
        return $this->hasMany(JadwalKuliah::class, 'id_dosen');
    }

    // Relasi ke Mahasiswa (sebagai wali)
    public function mahasiswaWali(): HasMany
    {
        return $this->hasMany(Mahasiswa::class, 'id_dosen');
    }

    // Relasi ke Beban Ajar Dosen
    public function bebanAjar(): HasMany
    {
        return $this->hasMany(BebanAjarDosen::class, 'id_dosen');
    }

    // Relasi ke Dosen Kelas MK
    public function dosenKelasMk(): HasMany
    {
        return $this->hasMany(DosenKelasMk::class, 'id_dosen');
    }

    // Relasi ke Perwalian
    public function perwalian(): HasMany
    {
        return $this->hasMany(Perwalian::class, 'id_dosen');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
