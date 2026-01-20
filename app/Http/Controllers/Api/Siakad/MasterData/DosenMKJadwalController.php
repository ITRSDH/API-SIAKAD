<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use Illuminate\Http\Request;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\Ruang;
use Illuminate\Http\JsonResponse;
use App\Models\MasterData\KelasMK;
use Illuminate\Support\Facades\DB;
use App\Models\MasterData\Semester;
use App\Http\Controllers\Controller;
use App\Models\MasterData\DosenKelasMk;
use App\Models\MasterData\JadwalKuliah;

class DosenMKJadwalController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $dosenKelasMkList = DosenKelasMk::with([
                'dosen',           // Relasi ke model Dosen
                'kelasMk',         // Relasi ke model KelasMk
                'kelasMk.mataKuliah', // Relasi lanjutan
                'kelasMk.semester',   // Relasi lanjutan
                'kelasMk.jadwalKuliah', // Relasi ke JadwalKuliah melalui KelasMk
            ])->get();

            if ($dosenKelasMkList->isEmpty()) {
                return response()->json([
                    'message' => 'Data Dosen Kelas MK tidak ditemukan.',
                    'data' => [],
                ], 404);
            }

            return response()->json([
                'message' => 'Daftar Dosen Kelas MK berhasil diambil.',
                'data' => $dosenKelasMkList,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data.',
                'error' => $e->getMessage(), // Hanya tampilkan pesan error jika development
            ], 500);
        }
    }
}
