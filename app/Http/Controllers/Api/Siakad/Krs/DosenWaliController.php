<?php

namespace App\Http\Controllers\Api\Siakad\Krs;

use App\Http\Controllers\Controller;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\Mahasiswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DosenWaliController extends Controller
{
    public function daftarMahasiswa(Request $request): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();
            $dosen = Dosen::where('user_id', $user->id)->first();
            $jumalahmhs = Mahasiswa::where('id_dosen', $dosen->id)->count();
            $mahasiswa = Mahasiswa::where('id_dosen', $dosen->id)->select('id', 'nama_mahasiswa', 'nim')->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar Mahasiswa berhasil diambil.',
                'data' => [
                    'jumlah_mahasiswa' => $jumalahmhs,
                    'mahasiswa' => $mahasiswa
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
}
