<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Akademik\KRSDetail;
use App\Models\Akademik\KomponenPenilaian;
use App\Models\Akademik\PenilaianKelas;
use App\Models\Akademik\PertemuanKuliah;

class KelasKuliah extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'kelas_kuliah';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_prodi',
        'id_kurikulum_mata_kuliah',
        'id_semester',
        'nama_kelas',
        'kapasitas_peserta',
        'bahasan',
        'lingkup',
        'mode_kuliah',
        'tanggal_mulai_efektif',
        'tanggal_akhir_efektif',
    ];

    public function kurikulumMataKuliah(): BelongsTo
    {
        return $this->belongsTo(KurikulumMataKuliah::class, 'id_kurikulum_mata_kuliah');
    }

    public function dosen_pengajar(): HasMany
    {
        return $this->hasMany(DosenPengajarKelas::class, 'id_kelas_kuliah')
            ->orderBy('urutan', 'asc');
    }

    public function jadwal(): HasMany
    {
        return $this->hasMany(JadwalKuliah::class, 'id_kelas_kuliah');
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }

    public function krsDetail(): HasMany
    {
        return $this->hasMany(KRSDetail::class, 'id_kelas_kuliah');
    }

    public function komponenPenilaian(): HasMany
    {
        return $this->hasMany(KomponenPenilaian::class, 'id_kelas_kuliah')
            ->orderBy('urutan')
            ->orderBy('created_at');
    }

    public function penilaianKelas(): HasOne
    {
        return $this->hasOne(PenilaianKelas::class, 'id_kelas_kuliah');
    }

    public function pertemuanKuliah(): HasMany
    {
        return $this->hasMany(PertemuanKuliah::class, 'id_kelas_kuliah')
            ->orderBy('pertemuan_ke');
    }

    public function getPesertaTerdaftarCountAttribute(): int
    {
        return $this->krsDetail()
            ->where('status', KRSDetail::STATUS_TERDAFTAR)
            ->count();
    }

    public function isPenuh(): bool
    {
        if ($this->kapasitas_peserta === null) {
            return false;
        }

        return $this->peserta_terdaftar_count >= $this->kapasitas_peserta;
    }
}
