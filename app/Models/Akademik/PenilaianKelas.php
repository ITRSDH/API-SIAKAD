<?php

namespace App\Models\Akademik;

use App\Models\MasterData\KelasKuliah;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenilaianKelas extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REOPENED = 'reopened';

    protected $table = 'penilaian_kelas';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_kelas_kuliah',
        'status',
        'validated_at',
        'published_at',
        'published_by',
        'reopened_at',
        'reopened_by',
        'reopen_reason',
    ];

    protected $casts = [
        'validated_at' => 'datetime',
        'published_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'id_kelas_kuliah');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function canManageDraftData(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REOPENED], true);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function canBeReopened(): bool
    {
        return $this->isPublished();
    }

    public function markPublished(?string $userId = null): void
    {
        $this->update([
            'status' => self::STATUS_PUBLISHED,
            'validated_at' => now(),
            'published_at' => now(),
            'published_by' => $userId,
            'reopened_at' => null,
            'reopened_by' => null,
            'reopen_reason' => null,
        ]);
    }

    public function markReopened(?string $userId = null, ?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_REOPENED,
            'reopened_at' => now(),
            'reopened_by' => $userId,
            'reopen_reason' => $reason,
        ]);
    }
}
