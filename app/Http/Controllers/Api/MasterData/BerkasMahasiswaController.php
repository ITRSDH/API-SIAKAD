<?php

namespace App\Http\Controllers\Api\MasterData;

use Exception;
use App\Models\MasterData\Mahasiswa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\MasterData\BerkasMahasiswa;
use Illuminate\Validation\ValidationException;

class BerkasMahasiswaController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $berkasMahasiswas = BerkasMahasiswa::with(['mahasiswa'])->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar Berkas Mahasiswa',
                'data' => $berkasMahasiswas
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data berkas mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_mahasiswa' => 'required|exists:mahasiswa,id',
                'jenis_berkas' => 'required|string|max:255',
                'file_path' => 'required|string|max:500', // Sesuaikan dengan tempat penyimpanan
                'file_nama' => 'required|string|max:255',
                'tanggal_upload' => 'required|date',
            ]);

            $berkasMahasiswa = BerkasMahasiswa::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Berkas Mahasiswa berhasil ditambahkan.',
                'data' => $berkasMahasiswa
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
                'message' => 'Terjadi kesalahan saat menambahkan berkas mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $berkasMahasiswa = BerkasMahasiswa::with(['mahasiswa'])->find($id);

            if (!$berkasMahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Berkas Mahasiswa tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Berkas Mahasiswa',
                'data' => $berkasMahasiswa
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data berkas mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $berkasMahasiswa = BerkasMahasiswa::find($id);

            if (!$berkasMahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Berkas Mahasiswa tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_mahasiswa' => 'sometimes|exists:mahasiswa,id',
                'jenis_berkas' => 'sometimes|string|max:255',
                'file_path' => 'sometimes|string|max:500',
                'file_nama' => 'sometimes|string|max:255',
                'tanggal_upload' => 'sometimes|date',
            ]);

            $berkasMahasiswa->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Berkas Mahasiswa berhasil diperbarui.',
                'data' => $berkasMahasiswa
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
                'message' => 'Terjadi kesalahan saat memperbarui berkas mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $berkasMahasiswa = BerkasMahasiswa::find($id);

            if (!$berkasMahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Berkas Mahasiswa tidak ditemukan.'
                ], 404);
            }

            $berkasMahasiswa->delete();

            return response()->json([
                'success' => true,
                'message' => 'Berkas Mahasiswa berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus berkas mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
