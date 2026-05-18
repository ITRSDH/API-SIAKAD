<?php

namespace App\Models\Akademik;

use App\Models\MasterData\KelasKuliah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PertemuanKuliah extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_TERJADWAL = 'terjadwal';
    public const STATUS_SELESAI = 'selesai';
    public const STATUS_DIBATALKAN = 'dibatalkan';

    protected $table = 'pertemuan_kuliah';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_kelas_kuliah',
        'pertemuan_ke',
        'judul_pertemuan',
        'tanggal_pertemuan',
        'materi',
        'catatan',
        'status',
    ];

    protected $casts = [
        'pertemuan_ke' => 'integer',
        'tanggal_pertemuan' => 'date',
    ];

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'id_kelas_kuliah');
    }

    public function presensi(): HasMany
    {
        return $this->hasMany(PresensiKuliah::class, 'id_pertemuan_kuliah');
    }

    // Business logic methods
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isTerjadwal(): bool
    {
        return $this->status === self::STATUS_TERJADWAL;
    }

    public function isSelesai(): bool
    {
        return $this->status === self::STATUS_SELESAI;
    }

    public function isDibatalkan(): bool
    {
        return $this->status === self::STATUS_DIBATALKAN;
    }

    public function canEdit(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_TERJADWAL], true);
    }

    public function canDelete(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canGeneratePresensi(): bool
    {
        return in_array($this->status, [self::STATUS_TERJADWAL, self::STATUS_SELESAI], true);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_TERJADWAL => 'Terjadwal',
            self::STATUS_SELESAI => 'Selesai',
            self::STATUS_DIBATALKAN => 'Dibatalkan',
            default => 'Unknown',
        };
    }

    public function scopeTerjadwal($query)
    {
        return $query->where('status', self::STATUS_TERJADWAL);
    }

    public function scopeSelesai($query)
    {
        return $query->where('status', self::STATUS_SELESAI);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }
}
