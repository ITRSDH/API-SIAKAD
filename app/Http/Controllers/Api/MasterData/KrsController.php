<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\Krs;
use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\Semester;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class KrsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $krss = Krs::with(['mahasiswa', 'semester'])->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar KRS',
                'data' => $krss
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data KRS.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_mahasiswa' => 'required|exists:mahasiswa,id',
                'id_semester' => 'required|exists:semester,id',
                'tanggal_pengisian' => 'required|date',
                'status' => 'required|in:Draft,Disetujui,Ditolak,Selesai',
                'jumlah_sks_diambil' => 'required|integer|min:0|max:24', // Contoh batas SKS
            ]);

            $krs = Krs::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'KRS berhasil ditambahkan.',
                'data' => $krs
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
                'message' => 'Terjadi kesalahan saat menambahkan KRS.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $krs = Krs::with(['mahasiswa', 'semester', 'krsDetail.kelasMk.mataKuliah'])->find($id);

            if (!$krs) {
                return response()->json([
                    'success' => false,
                    'message' => 'KRS tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail KRS',
                'data' => $krs
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data KRS.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $krs = Krs::find($id);

            if (!$krs) {
                return response()->json([
                    'success' => false,
                    'message' => 'KRS tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_mahasiswa' => 'sometimes|exists:mahasiswa,id',
                'id_semester' => 'sometimes|exists:semester,id',
                'tanggal_pengisian' => 'sometimes|date',
                'status' => 'sometimes|in:Draft,Disetujui,Ditolak,Selesai',
                'jumlah_sks_diambil' => 'sometimes|integer|min:0|max:24',
            ]);

            $krs->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'KRS berhasil diperbarui.',
                'data' => $krs
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
                'message' => 'Terjadi kesalahan saat memperbarui KRS.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $krs = Krs::find($id);

            if (!$krs) {
                return response()->json([
                    'success' => false,
                    'message' => 'KRS tidak ditemukan.'
                ], 404);
            }

            $krs->delete();

            return response()->json([
                'success' => true,
                'message' => 'KRS berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus KRS.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
