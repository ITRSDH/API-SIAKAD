<?php

namespace App\Http\Controllers\Api\MasterData;

use Exception;
use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\KelasPararel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\MasterData\KelasMahasiswa;
use Illuminate\Validation\ValidationException;

class KelasMahasiswaController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $kelasMahasiswas = KelasMahasiswa::with(['kelasPararel', 'mahasiswa'])->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar Kelas Mahasiswa',
                'data' => $kelasMahasiswas
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data kelas mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_kelas_pararel' => 'required|exists:kelas_pararel,id',
                'id_mahasiswa' => 'required|exists:mahasiswa,id',
                // Tambahkan unique jika satu mahasiswa hanya boleh satu kelas pararel
                // 'id_mahasiswa' => 'required|exists:mahasiswa,id|unique:kelas_mahasiswa,id_mahasiswa,NULL,id,id_kelas_pararel,' . $request->id_kelas_pararel,
            ]);

            $kelasMahasiswa = KelasMahasiswa::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Kelas Mahasiswa berhasil ditambahkan.',
                'data' => $kelasMahasiswa
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
                'message' => 'Terjadi kesalahan saat menambahkan kelas mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $kelasMahasiswa = KelasMahasiswa::with(['kelasPararel', 'mahasiswa'])->find($id);

            if (!$kelasMahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas Mahasiswa tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Kelas Mahasiswa',
                'data' => $kelasMahasiswa
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data kelas mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $kelasMahasiswa = KelasMahasiswa::find($id);

            if (!$kelasMahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas Mahasiswa tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_kelas_pararel' => 'sometimes|exists:kelas_pararel,id',
                'id_mahasiswa' => 'sometimes|exists:mahasiswa,id',
            ]);

            $kelasMahasiswa->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Kelas Mahasiswa berhasil diperbarui.',
                'data' => $kelasMahasiswa
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
                'message' => 'Terjadi kesalahan saat memperbarui kelas mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $kelasMahasiswa = KelasMahasiswa::find($id);

            if (!$kelasMahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas Mahasiswa tidak ditemukan.'
                ], 404);
            }

            $kelasMahasiswa->delete();

            return response()->json([
                'success' => true,
                'message' => 'Kelas Mahasiswa berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus kelas mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
