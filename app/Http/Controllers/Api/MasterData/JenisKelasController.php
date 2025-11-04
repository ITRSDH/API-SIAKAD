<?php

namespace App\Http\Controllers\Api\MasterData;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\MasterData\JenisKelas;
use Illuminate\Validation\ValidationException;

class JenisKelasController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $jenisKelas = JenisKelas::all();
            return response()->json([
                'success' => true,
                'message' => 'Daftar Jenis Kelas',
                'data' => $jenisKelas
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data jenis kelas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'nama_kelas' => 'required|string|max:255',
                'deskripsi' => 'nullable|string',
            ]);

            $jenisKelas = JenisKelas::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Jenis Kelas berhasil ditambahkan.',
                'data' => $jenisKelas
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
                'message' => 'Terjadi kesalahan saat menambahkan jenis kelas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $jenisKelas = JenisKelas::find($id);

            if (!$jenisKelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis Kelas tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Jenis Kelas',
                'data' => $jenisKelas
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data jenis kelas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $jenisKelas = JenisKelas::find($id);

            if (!$jenisKelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis Kelas tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'nama_kelas' => 'sometimes|string|max:255',
                'deskripsi' => 'nullable|string',
            ]);

            $jenisKelas->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Jenis Kelas berhasil diperbarui.',
                'data' => $jenisKelas
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
                'message' => 'Terjadi kesalahan saat memperbarui jenis kelas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $jenisKelas = JenisKelas::find($id);

            if (!$jenisKelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis Kelas tidak ditemukan.'
                ], 404);
            }

            $jenisKelas->delete();

            return response()->json([
                'success' => true,
                'message' => 'Jenis Kelas berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus jenis kelas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
