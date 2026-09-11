<?php

namespace App\Models\MasterData;

use App\Models\Akademik\Kelulusan;
use App\Models\Akademik\KHS;
use App\Models\Akademik\PesertaWisuda;
use App\Models\Akademik\Transkrip;
use App\Models\Akademik\TugasAkhir;
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
        'nisn',
        'kewarganegaraan',
        'npwp',
        'nama_mahasiswa',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'tanggal_masuk',
        'alamat',
        'alamat_jalan',
        'rt',
        'rw',
        'dusun',
        'kelurahan',
        'kode_pos',
        'id_wilayah',
        'jenis_tinggal',
        'alat_transportasi',
        'handphone',
        'email_pribadi',
        'nama_ibu_kandung',
        'nik_ibu',
        'tanggal_lahir_ibu',
        'pendidikan_ibu',
        'pekerjaan_ibu',
        'penghasilan_ibu',
        'nama_ayah',
        'nik_ayah',
        'tanggal_lahir_ayah',
        'pendidikan_ayah',
        'pekerjaan_ayah',
        'penghasilan_ayah',
        'nama_wali',
        'pendidikan_wali',
        'pekerjaan_wali',
        'penghasilan_wali',
        'agama',
        'status',
        'angkatan',
        'jenis_pendaftaran',
        'jalur_masuk',
        'sistem_pembiayaan',
        'id_periode_masuk',
        'perguruan_tinggi_asal',
        'prodi_asal',
        'sks_diakui',
        'penerima_kps',
        'nomor_kps',
        'kebutuhan_khusus',
        'id_mahasiswa_pddikti',
        'id_registrasi_mahasiswa_pddikti',
        'sync_status',
        'last_synced_at',
        'last_sync_error',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'tanggal_masuk' => 'date',
        'tanggal_lahir_ibu' => 'date',
        'tanggal_lahir_ayah' => 'date',
        'angkatan' => 'integer',
        'sks_diakui' => 'integer',
        'penerima_kps' => 'boolean',
        'last_synced_at' => 'datetime',
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
        if (! $currentKurikulumId) {
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

    public function nilaiTransfer(): HasMany
    {
        return $this->hasMany(\App\Models\Akademik\NilaiTransfer::class, 'id_mahasiswa');
    }

    protected static function booted(): void
    {
        static::saving(function (Mahasiswa $mahasiswa) {
            // Otomatis deteksi mahasiswa RPL dari akhiran NIM ('B' / 'b')
            if (! empty($mahasiswa->nim)) {
                $trimNim = strtoupper(trim((string) $mahasiswa->nim));
                if (str_ends_with($trimNim, 'B')) {
                    if (empty($mahasiswa->jenis_pendaftaran) || $mahasiswa->jenis_pendaftaran === 'Reguler') {
                        $mahasiswa->jenis_pendaftaran = 'RPL';
                    }
                    if (empty($mahasiswa->jalur_masuk) || $mahasiswa->jalur_masuk === 'Reguler') {
                        $mahasiswa->jalur_masuk = 'RPL';
                    }
                }
            }
        });
    }

    public function scopeReguler($query)
    {
        return $query->where(function ($q) {
            $q->where('jenis_pendaftaran', 'Reguler')
                ->where('nim', 'NOT LIKE', '%B')
                ->where('nim', 'NOT LIKE', '%b');
        });
    }

    public function scopeRpl($query)
    {
        return $query->where(function ($q) {
            $q->whereIn('jenis_pendaftaran', ['RPL', 'Pindahan'])
                ->orWhere('nim', 'LIKE', '%B')
                ->orWhere('nim', 'LIKE', '%b')
                ->orWhere('jalur_masuk', 'RPL');
        });
    }

    public function scopeBelumSync($query)
    {
        return $query->where('sync_status', 'belum');
    }

    public function isRpl(): bool
    {
        return in_array($this->jenis_pendaftaran, ['RPL', 'Pindahan'], true)
            || str_ends_with(strtoupper(trim((string) $this->nim)), 'B')
            || strtoupper(trim((string) ($this->jalur_masuk ?? ''))) === 'RPL';
    }
}
