<?php

namespace App\Http\Controllers\Api\MasterData;

use Exception;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\Ruang;
use App\Models\MasterData\KelasMk;
use App\Models\MasterData\Semester;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\MasterData\JadwalKuliah;
use Illuminate\Validation\ValidationException;

class JadwalKuliahController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $jadwalKuliahs = JadwalKuliah::with(['kelasMk', 'dosen', 'ruang', 'semester'])->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar Jadwal Kuliah',
                'data' => $jadwalKuliahs
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data jadwal kuliah.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_kelas_mk' => 'required|exists:kelas_mk,id',
                'id_dosen' => 'required|exists:dosen,id',
                'id_ruang' => 'required|exists:ruang,id',
                'id_semester' => 'required|exists:semester,id',
                'hari' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu',
                'jam_mulai' => 'required|date_format:H:i',
                'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            ]);

            $jadwalKuliah = JadwalKuliah::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Jadwal Kuliah berhasil ditambahkan.',
                'data' => $jadwalKuliah
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menambahkan jadwal kuliah.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $jadwalKuliah = JadwalKuliah::with(['kelasMk', 'dosen', 'ruang', 'semester'])->find($id);

            if (!$jadwalKuliah) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal Kuliah tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Jadwal Kuliah',
                'data' => $jadwalKuliah
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data jadwal kuliah.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $jadwalKuliah = JadwalKuliah::find($id);

            if (!$jadwalKuliah) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal Kuliah tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_kelas_mk' => 'sometimes|exists:kelas_mk,id',
                'id_dosen' => 'sometimes|exists:dosen,id',
                'id_ruang' => 'sometimes|exists:ruang,id',
                'id_semester' => 'sometimes|exists:semester,id',
                'hari' => 'sometimes|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu',
                'jam_mulai' => 'sometimes|date_format:H:i',
                'jam_selesai' => 'sometimes|date_format:H:i|after:jam_mulai',
            ]);

            $jadwalKuliah->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Jadwal Kuliah berhasil diperbarui.',
                'data' => $jadwalKuliah
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui jadwal kuliah.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $jadwalKuliah = JadwalKuliah::find($id);

            if (!$jadwalKuliah) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal Kuliah tidak ditemukan.'
                ], 404);
            }

            $jadwalKuliah->delete();

            return response()->json([
                'success' => true,
                'message' => 'Jadwal Kuliah berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus jadwal kuliah.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
