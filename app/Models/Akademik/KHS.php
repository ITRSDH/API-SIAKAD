<?php

namespace App\Models\Akademik;

use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\Semester;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class KHS extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'khs';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_mahasiswa',
        'id_semester',
        'total_sks_diambil',
        'total_sks_lulus',
        'ips',
        'ipk',
        'keterangan',
        'is_final',
        'updated_by',
        'finalized_by',
        'finalized_at',
        'generated_at',
    ];

    protected $casts = [
        'total_sks_diambil' => 'integer',
        'total_sks_lulus' => 'integer',
        'ips' => 'decimal:2',
        'ipk' => 'decimal:2',
        'keterangan' => 'string',
        'is_final' => 'boolean',
        'finalized_at' => 'datetime',
        'generated_at' => 'datetime',
    ];

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(KHSDetail::class, 'id_khs');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(KhsRevision::class, 'id_khs')
            ->orderByDesc('revision_number')
            ->orderByDesc('created_at');
    }
}
