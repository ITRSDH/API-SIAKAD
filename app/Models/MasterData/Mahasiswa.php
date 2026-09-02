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

    public function getCurrentKurikulumId(): ?string
    {
        // Mahasiswa tidak terikat langsung ke kurikulum. Struktur kurikulum
        // (sebagai struktur mata kuliah) disaring berdasarkan prodi + angkatan.
        return app(\App\Services\MahasiswaCurriculumContextService::class)
            ->resolveMatchingKurikulumId($this->id_prodi, $this->angkatan);
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
