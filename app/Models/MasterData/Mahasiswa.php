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
        if ($this->relationLoaded('riwayatKurikulumAktif')) {
            return $this->riwayatKurikulumAktif?->id_kurikulum;
        }

        if ($this->relationLoaded('riwayatKurikulum')) {
            $activeHistoryId = $this->riwayatKurikulum
                ->firstWhere('is_active', true)?->id_kurikulum;

            if (filled($activeHistoryId)) {
                return $activeHistoryId;
            }
        }

        $activeHistoryId = $this->riwayatKurikulumAktif()->value('id_kurikulum');
        if (filled($activeHistoryId)) {
            return $activeHistoryId;
        }

        return null;
    }

    public function getCurrentKurikulumIndukId(): ?string
    {
        if ($this->relationLoaded('riwayatKurikulumAktif')) {
            $activeIndukId = $this->riwayatKurikulumAktif?->id_kurikulum_induk;
            if (filled($activeIndukId)) {
                return $activeIndukId;
            }
        }

        if ($this->relationLoaded('riwayatKurikulum')) {
            $activeIndukId = $this->riwayatKurikulum
                ->firstWhere('is_active', true)?->id_kurikulum_induk;

            if (filled($activeIndukId)) {
                return $activeIndukId;
            }
        }

        $activeHistoryIndukId = $this->riwayatKurikulumAktif()->value('id_kurikulum_induk');
        if (filled($activeHistoryIndukId)) {
            return $activeHistoryIndukId;
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
