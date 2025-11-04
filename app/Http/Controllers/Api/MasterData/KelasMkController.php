<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\KelasMk;
use App\Models\MasterData\MataKuliah;
use App\Models\MasterData\KelasPararel;
use App\Models\MasterData\Semester;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class KelasMkController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            // Memuat relasi yang relevan
            $kelasMks = KelasMk::with(['mataKuliah', 'kelasPararel', 'semester'])->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar Kelas MK',
                'data' => $kelasMks
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data kelas MK.',
                'error' => $e->getMessage() // Hanya tampilkan pesan error jika debug=true
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $kelasMk = KelasMk::with(['mataKuliah', 'kelasPararel', 'semester'])->find($id);

            if (!$kelasMk) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas MK tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Kelas MK',
                'data' => $kelasMk
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data kelas MK.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_mk' => 'required|exists:mata_kuliah,id',
                'id_kelas_pararel' => 'required|exists:kelas_pararel,id',
                'id_semester' => 'required|exists:semester,id',
                'kode_kelas_mk' => 'required|string|max:255|unique:kelas_mk,kode_kelas_mk',
                'kuota' => 'required|integer|min:0',
                // Tambahkan validasi untuk field lain jika ada
            ]);

            $kelasMk = KelasMk::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Kelas MK berhasil dibuat.',
                'data' => $kelasMk
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
                'message' => 'Terjadi kesalahan saat membuat kelas MK.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $kelasMk = KelasMk::find($id);

            if (!$kelasMk) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas MK tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_mk' => 'sometimes|exists:mata_kuliah,id',
                'id_kelas_pararel' => 'sometimes|exists:kelas_pararel,id',
                'id_semester' => 'sometimes|exists:semester,id',
                'kode_kelas_mk' => 'sometimes|string|max:255|unique:kelas_mk,kode_kelas_mk,' . $id,
                'kuota' => 'sometimes|integer|min:0',
                // Tambahkan validasi untuk field lain jika ada
            ]);

            $kelasMk->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Kelas MK berhasil diperbarui.',
                'data' => $kelasMk
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
                'message' => 'Terjadi kesalahan saat memperbarui kelas MK.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $kelasMk = KelasMk::find($id);

            if (!$kelasMk) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas MK tidak ditemukan.'
                ], 404);
            }

            $kelasMk->delete();

            return response()->json([
                'success' => true,
                'message' => 'Kelas MK berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus kelas MK.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
