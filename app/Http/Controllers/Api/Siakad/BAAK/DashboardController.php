<?php

namespace App\Http\Controllers\Api\Siakad\BAAK;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\MasterData\JenisPembayaran;
use App\Models\MasterData\PembayaranMahasiswa;

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

        if (!$user->hasRole('baak')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. BAK access required.'
            ], 403);
        }

        try {
            $totalPembayaran = PembayaranMahasiswa::count();
            $totalJenisPembayaran = JenisPembayaran::count();
            $totalPembayaranLunas = PembayaranMahasiswa::where('status_pembayaran', 'Lunas')->count();
            $totalPembayaranBelumLunas = PembayaranMahasiswa::where('status_pembayaran', 'Belum Lunas')->count();

            $data = [
                'total_pembayaran' => $totalPembayaran,
                'total_jenis_pembayaran' => $totalJenisPembayaran,
                'total_pembayaran_lunas' => $totalPembayaranLunas,
                'total_pembayaran_belum_lunas' => $totalPembayaranBelumLunas,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Dashboard data for BAK.',
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
