<?php

namespace App\Models\Akademik;

use App\Models\MasterData\Semester;
use App\Models\MasterData\TahunAkademik;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KrsCollectiveBatch extends Model
{
    use HasFactory;
    use HasUuids;

    public const CONTEXT_HISTORICAL_STUDY = 'historical_study';

    public const ACTION_BUILD_HISTORICAL_KRS = 'build_historical_krs';
    public const ACTION_REOPEN_HISTORICAL_KRS = 'reopen_historical_krs';
    public const ACTION_REFINALIZE_HISTORICAL_KRS = 'refinalize_historical_krs';
    public const ACTION_RESET_HISTORICAL_KRS = 'reset_historical_krs';
    public const ACTION_GENERATE_KHS = 'generate_khs';

    protected $table = 'krs_collective_batches';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'created_by',
        'id_tahun_akademik',
        'id_semester',
        'context_type',
        'action_type',
        'filters',
        'payload',
        'summary',
        'notes',
        'executed_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'payload' => 'array',
        'summary' => 'array',
        'executed_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class, 'id_tahun_akademik');
    }

    public function items(): HasMany
    {
        return $this->hasMany(KrsCollectiveBatchItem::class, 'id_batch');
    }
}
