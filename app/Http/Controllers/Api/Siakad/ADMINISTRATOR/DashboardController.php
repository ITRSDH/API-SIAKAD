<?php

namespace App\Http\Controllers\Api\Siakad\ADMINISTRATOR;

use Illuminate\Http\Request;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\Prodi;
use App\Http\Controllers\Controller;
use App\Models\MasterData\Mahasiswa;
use Illuminate\Support\Facades\Auth;
use App\Models\MasterData\PembayaranMahasiswa;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Pengecekan otentikasi dan role secara manual
        $user = Auth::guard('api')->user(); // Gunakan guard 'api' jika menggunakan JWT

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }

        // Cek apakah user memiliki role 'admin' (menggunakan Spatie)
        if (!$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        // Jika pengecekan lolos, lanjutkan ke logika dashboard
        try {
            $totalProdi = Prodi::count();
            $totalDosen = Dosen::count();
            $totalMahasiswa = Mahasiswa::count();
            $totalPembayaran = PembayaranMahasiswa::count();

            $data = [
                'total_prodi' => $totalProdi,
                'total_dosen' => $totalDosen,
                'total_mahasiswa' => $totalMahasiswa,
                'total_pembayaran' => $totalPembayaran,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Dashboard data for Admin.',
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
