<?php

namespace App\Http\Controllers\Api\MasterData;

use Exception;
use Illuminate\Http\Request;
use App\Models\MasterData\Nilai;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Models\MasterData\KelasMk;
use App\Models\MasterData\Semester;
use App\Models\MasterData\Mahasiswa;

class NilaiController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            // Memuat relasi yang relevan
            $nilais = Nilai::with(['kelasMk.mataKuliah', 'mahasiswa', 'semester'])->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar Nilai',
                'data' => $nilais
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data nilai.',
                'error' => $e->getMessage() // Hanya tampilkan pesan error jika debug=true
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $nilai = Nilai::with(['kelasMk.mataKuliah', 'mahasiswa', 'semester'])->find($id);

            if (!$nilai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nilai tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Nilai',
                'data' => $nilai
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data nilai.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_kelas_mk' => 'required|exists:kelas_mk,id',
                'id_mahasiswa' => 'required|exists:mahasiswa,id',
                'id_semester' => 'required|exists:semester,id',
                'nilai_angka' => 'required|numeric|min:0|max:100', // Sesuaikan range jika perlu
                'nilai_huruf' => 'required|string|max:2',
                'bobot' => 'required|numeric|min:0|max:4.00', // Sesuaikan range jika perlu
                // Tambahkan validasi untuk field lain jika ada
            ]);

            $nilai = Nilai::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Nilai berhasil dibuat.',
                'data' => $nilai
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
                'message' => 'Terjadi kesalahan saat membuat nilai.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $nilai = Nilai::find($id);

            if (!$nilai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nilai tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_kelas_mk' => 'sometimes|exists:kelas_mk,id',
                'id_mahasiswa' => 'sometimes|exists:mahasiswa,id',
                'id_semester' => 'sometimes|exists:semester,id',
                'nilai_angka' => 'sometimes|numeric|min:0|max:100',
                'nilai_huruf' => 'sometimes|string|max:2',
                'bobot' => 'sometimes|numeric|min:0|max:4.00',
                // Tambahkan validasi untuk field lain jika ada
            ]);

            $nilai->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Nilai berhasil diperbarui.',
                'data' => $nilai
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
                'message' => 'Terjadi kesalahan saat memperbarui nilai.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $nilai = Nilai::find($id);

            if (!$nilai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nilai tidak ditemukan.'
                ], 404);
            }

            $nilai->delete();

            return response()->json([
                'success' => true,
                'message' => 'Nilai berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus nilai.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
