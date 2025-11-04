<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\DosenKelasMk;
use App\Models\MasterData\KelasMk;
use App\Models\MasterData\Dosen;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class DosenKelasMkController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $dosenKelasMks = DosenKelasMk::with(['kelasMk', 'dosen'])->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar Dosen Kelas MK',
                'data' => $dosenKelasMks
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data dosen kelas MK.',
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
                'peran' => 'required|in:Koordinator,Asisten',
            ]);

            $dosenKelasMk = DosenKelasMk::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Dosen Kelas MK berhasil ditambahkan.',
                'data' => $dosenKelasMk
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
                'message' => 'Terjadi kesalahan saat menambahkan dosen kelas MK.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $dosenKelasMk = DosenKelasMk::with(['kelasMk', 'dosen'])->find($id);

            if (!$dosenKelasMk) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dosen Kelas MK tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Dosen Kelas MK',
                'data' => $dosenKelasMk
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data dosen kelas MK.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $dosenKelasMk = DosenKelasMk::find($id);

            if (!$dosenKelasMk) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dosen Kelas MK tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_kelas_mk' => 'sometimes|exists:kelas_mk,id',
                'id_dosen' => 'sometimes|exists:dosen,id',
                'peran' => 'sometimes|in:Koordinator,Asisten',
            ]);

            $dosenKelasMk->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Dosen Kelas MK berhasil diperbarui.',
                'data' => $dosenKelasMk
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
                'message' => 'Terjadi kesalahan saat memperbarui dosen kelas MK.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $dosenKelasMk = DosenKelasMk::find($id);

            if (!$dosenKelasMk) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dosen Kelas MK tidak ditemukan.'
                ], 404);
            }

            $dosenKelasMk->delete();

            return response()->json([
                'success' => true,
                'message' => 'Dosen Kelas MK berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus dosen kelas MK.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
