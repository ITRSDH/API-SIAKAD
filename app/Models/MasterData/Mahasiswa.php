<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mahasiswa extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'mahasiswa'; // Singular
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id_prodi',
        'id_kelas_pararel',
        'id_dosen',
        'nim',
        'nama_mahasiswa',
        'jenis_kelamin',
        'tanggal_lahir',
        'alamat',
        'no_hp',
        'email',
        'asal_sekolah',
        'nama_orang_tua',
        'no_hp_orang_tua',
        'status',
        'angkatan',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
    ];

    // Relasi ke Prodi
    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }

    // Relasi ke Kelas Pararel
    public function kelasPararel(): BelongsTo
    {
        return $this->belongsTo(KelasPararel::class, 'id_kelas_pararel');
    }

    // Relasi ke Dosen (Wali)
    public function dosenWali(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'id_dosen');
    }

    // Relasi ke Kelas Mahasiswa
    public function kelasMahasiswa(): HasMany
    {
        return $this->hasMany(KelasMahasiswa::class, 'id_mahasiswa');
    }

    // Relasi ke Nilai
    public function nilai(): HasMany
    {
        return $this->hasMany(Nilai::class, 'id_mahasiswa');
    }

    // Relasi ke KRS
    public function krs(): HasMany
    {
        return $this->hasMany(Krs::class, 'id_mahasiswa');
    }

    // Relasi ke KHS
    public function khs(): HasMany
    {
        return $this->hasMany(Khs::class, 'id_mahasiswa');
    }

    // Relasi ke Presensi
    public function presensi(): HasMany
    {
        return $this->hasMany(Presensi::class, 'id_mahasiswa');
    }

    // Relasi ke Pembayaran Mahasiswa
    public function pembayaran(): HasMany
    {
        return $this->hasMany(PembayaranMahasiswa::class, 'id_mahasiswa');
    }

    // Relasi ke Status Akademik Mahasiswa
    public function statusAkademik(): HasMany
    {
        return $this->hasMany(StatusAkademikMahasiswa::class, 'id_mahasiswa');
    }

    // Relasi ke Perwalian
    public function perwalian(): HasMany
    {
        return $this->hasMany(Perwalian::class, 'id_mahasiswa');
    }

    // Relasi ke Berkas Mahasiswa
    public function berkas(): HasMany
    {
        return $this->hasMany(BerkasMahasiswa::class, 'id_mahasiswa');
    }

    // Relasi ke Alumni
    public function alumni(): HasMany
    {
        return $this->hasMany(Alumni::class, 'id_mahasiswa');
    }
}
