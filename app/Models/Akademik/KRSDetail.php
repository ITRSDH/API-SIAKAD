<?php

namespace App\Models\Akademik;

use App\Models\MasterData\KelasKuliah;
use App\Models\MasterData\MataKuliah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class KRSDetail extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'krs_detail';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_krs',
        'id_kelas_kuliah',
        'id_mata_kuliah',
        'id_import_batch',
        'status',
        'catatan',
        'nilai_akhir',
        'nilai_huruf',
        'bobot_nilai',
        'mutu',
    ];

    protected $casts = [
        'nilai_akhir' => 'decimal:2',
        'bobot_nilai' => 'decimal:2',
        'mutu' => 'decimal:2',
    ];

    const STATUS_TERDAFTAR = 'terdaftar';
    const STATUS_DROP = 'drop';
    const STATUS_LULUS = 'lulus';
    const STATUS_TIDAK_LULUS = 'tidak_lulus';

    // Relasi ke KRS
    public function krs(): BelongsTo
    {
        return $this->belongsTo(KRS::class, 'id_krs');
    }

    // Relasi ke Kelas Kuliah
    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'id_kelas_kuliah');
    }

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class, 'id_mata_kuliah');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(KhsImportBatch::class, 'id_import_batch');
    }

    public function nilaiKomponen(): HasMany
    {
        return $this->hasMany(NilaiKomponen::class, 'id_krs_detail');
    }

    public function presensiKuliah(): HasMany
    {
        return $this->hasMany(PresensiKuliah::class, 'id_krs_detail');
    }

    public function remedial(): HasMany
    {
        return $this->hasMany(Remedial::class, 'id_krs_detail')
            ->orderBy('attempt_ke');
    }

    // Scope untuk status
    public function scopeTerdaftar($query)
    {
        return $query->where('status', self::STATUS_TERDAFTAR);
    }

    public function scopeDrop($query)
    {
        return $query->where('status', self::STATUS_DROP);
    }

    public function scopeLulus($query)
    {
        return $query->where('status', self::STATUS_LULUS);
    }

    public function isFinalScored(): bool
    {
        if ($this->status === self::STATUS_DROP) {
            return false;
        }

        return $this->nilai_akhir !== null
            && $this->nilai_huruf !== null
            && $this->resolveMutuValue() !== null
            && $this->resolveWeightedBobotNilaiValue() !== null
            && in_array($this->status, [self::STATUS_LULUS, self::STATUS_TIDAK_LULUS], true);
    }

    public function isCountedInKhs(): bool
    {
        return in_array($this->status, [self::STATUS_LULUS, self::STATUS_TIDAK_LULUS], true);
    }

    // Method untuk mendapatkan SKS
    public function getSksAttribute()
    {
        return $this->kelasKuliah?->kurikulumMataKuliah?->mataKuliah?->sks ?? 0;
    }

    // Method untuk mendapatkan nama mata kuliah
    public function getNamaMataKuliahAttribute()
    {
        return $this->kelasKuliah?->kurikulumMataKuliah?->mataKuliah?->nama_mk ?? '';
    }

    // Method untuk mendapatkan kode mata kuliah
    public function getKodeMataKuliahAttribute()
    {
        return $this->kelasKuliah?->kurikulumMataKuliah?->mataKuliah?->kode_mk ?? '';
    }

    // Method untuk drop mata kuliah
    public function dropMataKuliah($catatan = null)
    {
        return $this->update([
            'status' => self::STATUS_DROP,
            'catatan' => $catatan,
        ]);
    }

    // Method untuk input nilai
    public function inputNilai($nilai_akhir, $nilai_huruf, $bobot_nilai)
    {
        $mutu = (float) $bobot_nilai;
        $status = $mutu >= 2.0 ? self::STATUS_LULUS : self::STATUS_TIDAK_LULUS;
        
        return $this->update([
            'nilai_akhir' => $nilai_akhir,
            'nilai_huruf' => $nilai_huruf,
            'mutu' => $mutu,
            'bobot_nilai' => round(((int) $this->sks) * $mutu, 2),
            'status' => $status,
            'id_mata_kuliah' => $this->resolveMataKuliahId(),
        ]);
    }

    public function syncFinalScoreFromKomponen(): array
    {
        $komponen = $this->nilaiKomponen()
            ->with('komponenPenilaian')
            ->get()
            ->filter(fn($item) => $item->komponenPenilaian && $item->komponenPenilaian->is_active);

        $nilaiAkhir = $komponen->sum(function ($item) {
            $nilai = $item->nilai ?? 0;
            $bobot = (float) ($item->komponenPenilaian->bobot ?? 0);

            return $nilai * ($bobot / 100);
        });

        $grading = self::convertNumericScore((float) $nilaiAkhir);

        $this->inputNilai(
            round($nilaiAkhir, 2),
            $grading['nilai_huruf'],
            $grading['bobot_nilai']
        );

        return [
            'nilai_akhir' => round($nilaiAkhir, 2),
            'nilai_huruf' => $grading['nilai_huruf'],
            'mutu' => $grading['bobot_nilai'],
            'bobot_nilai' => $this->fresh()->bobot_nilai,
            'status' => $this->fresh()->status,
        ];
    }

    public function resolveMataKuliahId(): ?string
    {
        return $this->id_mata_kuliah
            ?? $this->kelasKuliah?->kurikulumMataKuliah?->mataKuliah?->id;
    }

    public function resolveMutuValue(): ?float
    {
        if ($this->mutu !== null) {
            return round((float) $this->mutu, 2);
        }

        if ($this->bobot_nilai !== null && (float) $this->bobot_nilai <= 4.00) {
            return round((float) $this->bobot_nilai, 2);
        }

        $sks = (int) $this->sks;
        if ($this->bobot_nilai !== null && $sks > 0) {
            return round(((float) $this->bobot_nilai) / $sks, 2);
        }

        return null;
    }

    public function resolveWeightedBobotNilaiValue(): ?float
    {
        if ($this->bobot_nilai !== null && (float) $this->bobot_nilai > 4.00) {
            return round((float) $this->bobot_nilai, 2);
        }

        $mutu = $this->resolveMutuValue();
        if ($mutu === null) {
            return null;
        }

        return round(((int) $this->sks) * $mutu, 2);
    }

    public static function convertNumericScore(float $nilaiAkhir): array
    {
        return match (true) {
            $nilaiAkhir >= 85 => ['nilai_huruf' => 'A', 'bobot_nilai' => 4.00],
            $nilaiAkhir >= 80 => ['nilai_huruf' => 'A-', 'bobot_nilai' => 3.75],
            $nilaiAkhir >= 75 => ['nilai_huruf' => 'B+', 'bobot_nilai' => 3.50],
            $nilaiAkhir >= 70 => ['nilai_huruf' => 'B', 'bobot_nilai' => 3.00],
            $nilaiAkhir >= 65 => ['nilai_huruf' => 'B-', 'bobot_nilai' => 2.75],
            $nilaiAkhir >= 60 => ['nilai_huruf' => 'C+', 'bobot_nilai' => 2.50],
            $nilaiAkhir >= 55 => ['nilai_huruf' => 'C', 'bobot_nilai' => 2.00],
            $nilaiAkhir >= 40 => ['nilai_huruf' => 'D', 'bobot_nilai' => 1.00],
            default => ['nilai_huruf' => 'E', 'bobot_nilai' => 0.00],
        };
    }
}
