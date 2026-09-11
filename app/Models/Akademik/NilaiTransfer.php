<?php

namespace App\Models\Akademik;

use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\MataKuliah;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NilaiTransfer extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'nilai_transfer';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id_mahasiswa',
        'id_mata_kuliah',
        'kode_mata_kuliah_asal',
        'nama_mata_kuliah_asal',
        'sks_asal',
        'nilai_huruf_asal',
        'sks_diakui',
        'nilai_angka_diakui',
        'nilai_huruf_diakui',
        'nilai_indeks_diakui',
        'id_nilai_transfer_pddikti',
        'sync_status',
        'last_synced_at',
        'keterangan',
        'created_by',
    ];

    protected $casts = [
        'sks_asal' => 'decimal:2',
        'sks_diakui' => 'decimal:2',
        'nilai_angka_diakui' => 'decimal:2',
        'nilai_indeks_diakui' => 'decimal:2',
        'last_synced_at' => 'datetime',
    ];

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class, 'id_mata_kuliah');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
