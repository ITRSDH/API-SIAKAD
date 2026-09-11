<?php

namespace App\Http\Controllers\Api\Siakad\Administratif;

use App\Http\Controllers\Controller;
use App\Models\Setting\PddiktiSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PddiktiSettingController extends Controller
{
    /**
     * Dapatkan konfigurasi PDDikti Feeder saat ini.
     */
    public function index(): JsonResponse
    {
        $setting = PddiktiSetting::getActive();

        return response()->json([
            'success' => true,
            'data' => $setting,
        ]);
    }

    /**
     * Perbarui konfigurasi / toggle on-off Feeder.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sync_enabled' => 'nullable|boolean',
            'sync_mode' => 'nullable|in:manual,auto',
            'auto_sync_mahasiswa' => 'nullable|boolean',
            'auto_sync_krs' => 'nullable|boolean',
            'auto_sync_nilai' => 'nullable|boolean',
            'feeder_url' => 'nullable|url',
            'feeder_username' => 'nullable|string|max:100',
            'feeder_password' => 'nullable|string|max:255',
        ]);

        $setting = PddiktiSetting::getActive();
        $setting->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan integrasi Neo Feeder PDDikti berhasil disimpan.',
            'data' => $setting->fresh(),
        ]);
    }

    /**
     * Tes koneksi HTTP ke server Neo Feeder lokal / kampus.
     */
    public function testConnection(): JsonResponse
    {
        $setting = PddiktiSetting::getActive();

        if (empty($setting->feeder_url)) {
            return response()->json([
                'success' => false,
                'message' => 'URL server Feeder belum diatur.',
            ], 422);
        }

        try {
            $response = Http::timeout(5)->post($setting->feeder_url, [
                'act' => 'GetVersion',
            ]);

            if ($response->successful()) {
                $setting->update([
                    'last_connected_at' => now(),
                    'last_error_message' => null,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Koneksi ke Neo Feeder PDDikti berhasil.',
                    'version_info' => $response->json(),
                ]);
            }

            $errorMsg = 'Respon server Feeder: '.$response->status();
            $setting->update(['last_error_message' => $errorMsg]);

            return response()->json([
                'success' => false,
                'message' => $errorMsg,
            ], 502);
        } catch (\Exception $e) {
            $setting->update(['last_error_message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal terhubung ke Neo Feeder: '.$e->getMessage(),
            ], 500);
        }
    }
}
