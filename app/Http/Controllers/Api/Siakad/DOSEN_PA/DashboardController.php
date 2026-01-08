<?php

namespace App\Http\Controllers\Api\Siakad\DOSEN_PA;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\Krs;
use App\Models\MasterData\Perwalian;
use Illuminate\Support\Facades\Auth;

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

        if (!$user->hasRole('dosen_pa')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Dosen PA access required.'
            ], 403);
        }

        try {
            $dosenId = $user->dosen->id;

            $mahasiswaBimbinganIds = Mahasiswa::where('id_dosen', $dosenId)->pluck('id');
            $totalMahasiswaBimbingan = $mahasiswaBimbinganIds->count();
            $totalKrsDisetujui = Krs::whereIn('id_mahasiswa', $mahasiswaBimbinganIds)->where('status', 'Disetujui')->count();
            $totalPerwalian = Perwalian::where('id_dosen', $dosenId)->count();

            $stats = [
                'total_mahasiswa_bimbingan' => $totalMahasiswaBimbingan,
                'total_krs_disetujui' => $totalKrsDisetujui,
                'total_perwalian' => $totalPerwalian,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Dashboard data for Dosen PA.',
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
