<?php

namespace App\Http\Controllers\Api\Siakad\MAHASISWA;

use Illuminate\Http\Request;
use App\Models\MasterData\Presensi;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\MasterData\PembayaranMahasiswa;
use App\Models\MasterData\Krs;
use App\Models\MasterData\Khs;

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

        if (!$user->hasRole('mahasiswa')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Mahasiswa access required.'
            ], 403);
        }

        try {
            $mahasiswaId = $user->mahasiswa->id;

            $totalKrs = Krs::where('id_mahasiswa', $mahasiswaId)->count();
            $khsTerakhir = Khs::where('id_mahasiswa', $mahasiswaId)->latest('id_semester')->first();
            $totalPembayaran = PembayaranMahasiswa::where('id_mahasiswa', $mahasiswaId)->count();
            $totalPresensiHadir = Presensi::where('id_mahasiswa', $mahasiswaId)->where('status_hadir', 'Hadir')->count();

            $data = [
                'total_krs' => $totalKrs,
                'khs_terakhir' => $khsTerakhir ? $khsTerakhir->toArray() : null,
                'total_pembayaran' => $totalPembayaran,
                'total_presensi_hadir' => $totalPresensiHadir,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Dashboard data for Mahasiswa.',
                'data' => $data
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
