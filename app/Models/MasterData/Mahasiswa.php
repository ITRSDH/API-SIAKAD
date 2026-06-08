<?php

namespace App\Models\MasterData;

use App\Models\Akademik\KHS;
use App\Models\Akademik\Kelulusan;
use App\Models\Akademik\PesertaWisuda;
use App\Models\Akademik\PeriodeWisuda;
use App\Models\Akademik\TugasAkhir;
use App\Models\Akademik\Transkrip;
use App\Models\Akademik\Yudisium;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Mahasiswa extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'mahasiswa';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id_prodi',
        'id_kurikulum',
        'id_dosen',
        'user_id',
        'nim',
        'nik',
        'nama_mahasiswa',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'tanggal_masuk',
        'alamat',
        'agama',
        'status',
        'angkatan',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'tanggal_masuk' => 'date',
        'angkatan' => 'integer',
    ];

    // Relasi ke Prodi
    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class, 'id_kurikulum');
    }

    public function riwayatKurikulum(): HasMany
    {
        return $this->hasMany(RiwayatKurikulumMahasiswa::class, 'id_mahasiswa')
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('created_at');
    }

    public function riwayatKurikulumAktif(): HasOne
    {
        return $this->hasOne(RiwayatKurikulumMahasiswa::class, 'id_mahasiswa')
            ->where('is_active', true)
            ->latest('tanggal_mulai')
            ->latest('created_at');
    }

    public function getCurrentKurikulumId(): ?string
    {
        // Kurikulum mahasiswa pada current state disimpan langsung di tabel mahasiswa.
        // Riwayat kurikulum dipakai sebagai fallback bila relasi aktif sudah dimuat.
        if (filled($this->id_kurikulum)) {
            return $this->id_kurikulum;
        }

        if ($this->relationLoaded('riwayatKurikulumAktif')) {
            return $this->riwayatKurikulumAktif?->id_kurikulum;
        }

        if ($this->relationLoaded('riwayatKurikulum')) {
            return $this->riwayatKurikulum
                ->firstWhere('is_active', true)?->id_kurikulum;
        }

        return $this->riwayatKurikulumAktif()->value('id_kurikulum');
    }

    public function getCurrentKurikulumIndukId(): ?string
    {
        if ($this->relationLoaded('kurikulum') && filled($this->kurikulum?->id_kurikulum_induk)) {
            return $this->kurikulum?->id_kurikulum_induk;
        }

        $currentKurikulumId = $this->getCurrentKurikulumId();
        if (!$currentKurikulumId) {
            return null;
        }

        return Kurikulum::query()
            ->where('id', $currentKurikulumId)
            ->value('id_kurikulum_induk');
    }

    public function getCurrentKurikulum(): ?Kurikulum
    {
        $currentKurikulumId = $this->getCurrentKurikulumId();
        if (!$currentKurikulumId) {
            return null;
        }

        if ($this->relationLoaded('kurikulum') && $this->kurikulum?->id === $currentKurikulumId) {
            return $this->kurikulum;
        }

        return Kurikulum::find($currentKurikulumId);
    }

    public function getBaseKurikulumId(): ?string
    {
        return $this->getCurrentKurikulumId();
    }

    public function getBaseKurikulum(): ?Kurikulum
    {
        return $this->getCurrentKurikulum();
    }

    public function getActiveKurikulumId(): ?string
    {
        return $this->getCurrentKurikulumId();
    }

    public function getActiveKurikulum(): ?Kurikulum
    {
        return $this->getCurrentKurikulum();
    }

    // Relasi ke Dosen (Wali)
    public function dosenWali(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'id_dosen');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function khs(): HasMany
    {
        return $this->hasMany(KHS::class, 'id_mahasiswa');
    }

    public function transkrip(): HasMany
    {
        return $this->hasMany(Transkrip::class, 'id_mahasiswa');
    }

    public function yudisium(): HasMany
    {
        return $this->hasMany(Yudisium::class, 'id_mahasiswa');
    }

    public function kelulusan(): HasMany
    {
        return $this->hasMany(Kelulusan::class, 'id_mahasiswa');
    }

    public function tugasAkhir(): HasMany
    {
        return $this->hasMany(TugasAkhir::class, 'id_mahasiswa');
    }

    public function pesertaWisuda(): HasMany
    {
        return $this->hasMany(PesertaWisuda::class, 'id_mahasiswa');
    }
}
