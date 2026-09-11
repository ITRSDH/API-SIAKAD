<?php

namespace App\Models\Setting;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PddiktiSetting extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'pddikti_settings';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'sync_enabled',
        'sync_mode',
        'auto_sync_mahasiswa',
        'auto_sync_krs',
        'auto_sync_nilai',
        'feeder_url',
        'feeder_username',
        'feeder_password',
        'feeder_token',
        'last_connected_at',
        'last_error_message',
    ];

    protected $casts = [
        'sync_enabled' => 'boolean',
        'auto_sync_mahasiswa' => 'boolean',
        'auto_sync_krs' => 'boolean',
        'auto_sync_nilai' => 'boolean',
        'last_connected_at' => 'datetime',
    ];

    /**
     * Dapatkan konfigurasi aktif (singleton-style setting)
     */
    public static function getActive(): self
    {
        $setting = self::first();
        if (! $setting) {
            $setting = self::create([
                'sync_enabled' => false,
                'sync_mode' => 'manual',
                'auto_sync_mahasiswa' => false,
                'auto_sync_krs' => false,
                'auto_sync_nilai' => false,
                'feeder_url' => config('services.feeder.base_url', 'http://localhost:8100/ws/live.php'),
            ]);
        }

        return $setting;
    }
}
