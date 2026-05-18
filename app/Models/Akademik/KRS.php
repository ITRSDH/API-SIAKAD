<?php

namespace App\Models\Akademik;

use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\Semester;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class KRS extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'krs';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_mahasiswa',
        'id_semester',
        'tanggal_pengajuan',
        'status_approval',
        'approved_by',
        'tanggal_approval',
        'catatan',
        'total_sks',
        'is_locked',
        'is_sks_override',
        'sks_override_reason',
        'sks_override_by',
        'sks_override_at',
    ];

    protected $casts = [
        'tanggal_pengajuan' => 'datetime',
        'tanggal_approval' => 'datetime',
        'sks_override_at' => 'datetime',
        'is_locked' => 'boolean',
        'is_sks_override' => 'boolean',
        'total_sks' => 'integer',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_REVISED = 'revised';
    const EDITABLE_STATUSES = [
        self::STATUS_REVISED,
    ];

    // Relasi ke Mahasiswa
    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }

    // Relasi ke Semester
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }

    // Relasi ke Detail KRS
    public function details(): HasMany
    {
        return $this->hasMany(KRSDetail::class, 'id_krs');
    }

    // Relasi ke Dosen yang menyetujui
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\MasterData\Dosen::class, 'approved_by');
    }

    public function sksOverrideBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'sks_override_by');
    }

    // Scope untuk status approval
    public function scopePending($query)
    {
        return $query->where('status_approval', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status_approval', self::STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status_approval', self::STATUS_REJECTED);
    }

    public function scopeRevised($query)
    {
        return $query->where('status_approval', self::STATUS_REVISED);
    }

    // Method untuk menghitung total SKS
    public function calculateTotalSks()
    {
        return $this->details()
            ->with('kelasKuliah.kurikulumMataKuliah.mataKuliah')
            ->get()
            ->sum(function ($detail) {
                return $detail->kelasKuliah->kurikulumMataKuliah->mataKuliah->sks ?? 0;
            });
    }

    // Method untuk lock KRS
    public function lock()
    {
        $this->update([
            'is_locked' => true,
            'status_approval' => self::STATUS_APPROVED,
            'tanggal_approval' => now(),
        ]);
    }

    // Method untuk unlock KRS
    public function unlock()
    {
        $this->update([
            'is_locked' => false,
            'status_approval' => self::STATUS_REVISED,
            'tanggal_approval' => null,
        ]);
    }

    public function isEditable(): bool
    {
        return !$this->is_locked && in_array($this->status_approval, self::EDITABLE_STATUSES, true);
    }

    public function clearSksOverride(): void
    {
        $this->update([
            'is_sks_override' => false,
            'sks_override_reason' => null,
            'sks_override_by' => null,
            'sks_override_at' => null,
        ]);
    }
}
