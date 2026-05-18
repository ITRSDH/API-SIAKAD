<?php

namespace App\Models\Akademik;

use App\Models\MasterData\Kurikulum;
use App\Models\MasterData\Mahasiswa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TugasAkhir extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENGAJUAN = 'pengajuan';
    public const STATUS_BIMBINGAN = 'bimbingan';
    public const STATUS_UJIAN = 'ujian';
    public const STATUS_REVISI = 'revisi';
    public const STATUS_LULUS = 'lulus';
    public const STATUS_TIDAK_LULUS = 'tidak_lulus';
    public const STATUS_DIBATALKAN = 'dibatalkan';

    protected $table = 'tugas_akhir';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_mahasiswa',
        'id_kurikulum',
        'jenis_tugas_akhir',
        'judul',
        'topik',
        'status',
        'tanggal_pengajuan',
        'tanggal_mulai_bimbingan',
        'tanggal_lulus',
        'is_active',
        'catatan',
    ];

    protected $casts = [
        'tanggal_pengajuan' => 'date',
        'tanggal_mulai_bimbingan' => 'date',
        'tanggal_lulus' => 'date',
        'is_active' => 'boolean',
    ];

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class, 'id_kurikulum');
    }

    public function pembimbing(): HasMany
    {
        return $this->hasMany(TugasAkhirPembimbing::class, 'id_tugas_akhir')
            ->orderBy('created_at');
    }

    public function ujian(): HasMany
    {
        return $this->hasMany(TugasAkhirUjian::class, 'id_tugas_akhir')
            ->orderByDesc('tanggal_ujian')
            ->orderByDesc('created_at');
    }
}
