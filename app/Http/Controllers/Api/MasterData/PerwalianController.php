<?php

namespace App\Http\Controllers\Api\MasterData;

use Exception;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\Mahasiswa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\MasterData\Perwalian;
use Illuminate\Validation\ValidationException;

class PerwalianController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $perwalians = Perwalian::with(['mahasiswa', 'dosen'])->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar Perwalian',
                'data' => $perwalians
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data perwalian.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_mahasiswa' => 'required|exists:mahasiswa,id',
                'id_dosen' => 'required|exists:dosen,id',
                'tanggal_perwalian' => 'required|date',
                'status_perwalian' => 'required|in:Draf,Disetujui,Ditolak',
                'keterangan' => 'nullable|string',
            ]);

            $perwalian = Perwalian::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Perwalian berhasil ditambahkan.',
                'data' => $perwalian
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
                'message' => 'Terjadi kesalahan saat menambahkan perwalian.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $perwalian = Perwalian::with(['mahasiswa', 'dosen'])->find($id);

            if (!$perwalian) {
                return response()->json([
                    'success' => false,
                    'message' => 'Perwalian tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Perwalian',
                'data' => $perwalian
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data perwalian.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $perwalian = Perwalian::find($id);

            if (!$perwalian) {
                return response()->json([
                    'success' => false,
                    'message' => 'Perwalian tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_mahasiswa' => 'sometimes|exists:mahasiswa,id',
                'id_dosen' => 'sometimes|exists:dosen,id',
                'tanggal_perwalian' => 'sometimes|date',
                'status_perwalian' => 'sometimes|in:Draf,Disetujui,Ditolak',
                'keterangan' => 'nullable|string',
            ]);

            $perwalian->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Perwalian berhasil diperbarui.',
                'data' => $perwalian
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
                'message' => 'Terjadi kesalahan saat memperbarui perwalian.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $perwalian = Perwalian::find($id);

            if (!$perwalian) {
                return response()->json([
                    'success' => false,
                    'message' => 'Perwalian tidak ditemukan.'
                ], 404);
            }

            $perwalian->delete();

            return response()->json([
                'success' => true,
                'message' => 'Perwalian berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus perwalian.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
