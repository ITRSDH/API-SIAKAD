<?php

namespace App\Http\Controllers\Api\Siakad\DOSEN_PENGAMPU;

use Illuminate\Http\Request;
use App\Models\MasterData\Nilai;
use Illuminate\Support\Facades\DB;
use App\Models\MasterData\Presensi;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\MasterData\JadwalKuliah;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }

        if (!$user->hasRole('dosen_pengampu')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Dosen Pengampu access required.'
            ], 403);
        }

        try {
            $dosenId = $user->dosen->id;

            $kelasAmpuIds = DB::table('dosen_kelas_mk')
                ->where('id_dosen', $dosenId)
                ->pluck('id_kelas_mk');

            $totalKelasAmpu = $kelasAmpuIds->count();
            $totalJadwalAmpu = JadwalKuliah::whereIn('id_kelas_mk', $kelasAmpuIds)->count();
            $totalPresensiInput = Presensi::whereIn('id_kelas_mk', $kelasAmpuIds)->count();
            $totalNilaiInput = Nilai::whereIn('id_kelas_mk', $kelasAmpuIds)->count();

            $stats = [
                'total_kelas_ampu' => $totalKelasAmpu,
                'total_jadwal_ampu' => $totalJadwalAmpu,
                'total_presensi_input' => $totalPresensiInput,
                'total_nilai_input' => $totalNilaiInput,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Dashboard data for Dosen Pengampu.',
                'data' => $stats
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
