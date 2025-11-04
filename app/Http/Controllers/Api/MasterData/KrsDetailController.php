<?php

namespace App\Http\Controllers\Api\MasterData;

use Exception;
use App\Models\MasterData\Krs;
use App\Models\MasterData\KelasMk;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\MasterData\KrsDetail;
use Illuminate\Validation\ValidationException;

class KrsDetailController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $krsDetails = KrsDetail::with(['krs', 'kelasMk.mataKuliah'])->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar KRS Detail',
                'data' => $krsDetails
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data KRS Detail.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_krs' => 'required|exists:krs,id',
                'id_kelas_mk' => 'required|exists:kelas_mk,id',
                'sks_diambil' => 'required|integer|min:1|max:6',
            ]);

            $krsDetail = KrsDetail::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'KRS Detail berhasil ditambahkan.',
                'data' => $krsDetail
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
                'message' => 'Terjadi kesalahan saat menambahkan KRS Detail.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $krsDetail = KrsDetail::with(['krs', 'kelasMk.mataKuliah'])->find($id);

            if (!$krsDetail) {
                return response()->json([
                    'success' => false,
                    'message' => 'KRS Detail tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail KRS Detail',
                'data' => $krsDetail
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data KRS Detail.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $krsDetail = KrsDetail::find($id);

            if (!$krsDetail) {
                return response()->json([
                    'success' => false,
                    'message' => 'KRS Detail tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_krs' => 'sometimes|exists:krs,id',
                'id_kelas_mk' => 'sometimes|exists:kelas_mk,id',
                'sks_diambil' => 'sometimes|integer|min:1|max:6',
            ]);

            $krsDetail->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'KRS Detail berhasil diperbarui.',
                'data' => $krsDetail
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
                'message' => 'Terjadi kesalahan saat memperbarui KRS Detail.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $krsDetail = KrsDetail::find($id);

            if (!$krsDetail) {
                return response()->json([
                    'success' => false,
                    'message' => 'KRS Detail tidak ditemukan.'
                ], 404);
            }

            $krsDetail->delete();

            return response()->json([
                'success' => true,
                'message' => 'KRS Detail berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus KRS Detail.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
