<?php

namespace App\Http\Controllers\Api\Siakad\KAPRODI;

use Illuminate\Http\Request;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\Nilai;
use App\Http\Controllers\Controller;
use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\KelasMK;
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

        if (!$user->hasRole('kaprodi')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Kaprodi access required.'
            ], 403);
        }

        try {
            $dosen = $user->dosen; // Asumsi relasi 'dosen' di model User
            if (!$dosen) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data dosen tidak ditemukan untuk user ini.'
                ], 404);
            }

            $prodiId = $dosen->id_prodi;

            $totalDosenProdi = Dosen::where('id_prodi', $prodiId)->count();
            $totalMahasiswaProdi = Mahasiswa::where('id_prodi', $prodiId)->count();
            $totalKelasMkProdi = KelasMk::whereHas('mataKuliah.kurikulum.prodi', function ($query) use ($prodiId) {
                $query->where('id', $prodiId);
            })->count();
            $totalNilaiInput = Nilai::whereHas('kelasMk.mataKuliah.kurikulum.prodi', function ($query) use ($prodiId) {
                $query->where('id', $prodiId);
            })->count();

            $data = [
                'total_dosen' => $totalDosenProdi,
                'total_mahasiswa' => $totalMahasiswaProdi,
                'total_kelas_mk' => $totalKelasMkProdi,
                'total_nilai_input' => $totalNilaiInput,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Dashboard data for Kaprodi.',
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
