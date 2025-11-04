<?php

namespace App\Http\Controllers\Api\MasterData;

use Exception;
use App\Models\MasterData\Khs;
use App\Models\MasterData\Nilai;
use App\Models\MasterData\MataKuliah;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\MasterData\KhsDetail;
use Illuminate\Validation\ValidationException;

class KhsDetailController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $khsDetails = KhsDetail::with(['khs', 'mataKuliah', 'nilai'])->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar KHS Detail',
                'data' => $khsDetails
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data KHS Detail.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_khs' => 'required|exists:khs,id',
                'id_mk' => 'required|exists:mata_kuliah,id',
                'nilai_huruf' => 'required|string|size:2',
                'bobot' => 'required|numeric|min:0|max:4',
                'sks' => 'required|integer|min:1|max:6',
                'id_nilai' => 'nullable|exists:nilai,id',
            ]);

            $khsDetail = KhsDetail::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'KHS Detail berhasil ditambahkan.',
                'data' => $khsDetail
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
                'message' => 'Terjadi kesalahan saat menambahkan KHS Detail.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $khsDetail = KhsDetail::with(['khs', 'mataKuliah', 'nilai'])->find($id);

            if (!$khsDetail) {
                return response()->json([
                    'success' => false,
                    'message' => 'KHS Detail tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail KHS Detail',
                'data' => $khsDetail
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data KHS Detail.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $khsDetail = KhsDetail::find($id);

            if (!$khsDetail) {
                return response()->json([
                    'success' => false,
                    'message' => 'KHS Detail tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_khs' => 'sometimes|exists:khs,id',
                'id_mk' => 'sometimes|exists:mata_kuliah,id',
                'nilai_huruf' => 'sometimes|string|size:2',
                'bobot' => 'sometimes|numeric|min:0|max:4',
                'sks' => 'sometimes|integer|min:1|max:6',
                'id_nilai' => 'nullable|exists:nilai,id',
            ]);

            $khsDetail->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'KHS Detail berhasil diperbarui.',
                'data' => $khsDetail
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
                'message' => 'Terjadi kesalahan saat memperbarui KHS Detail.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $khsDetail = KhsDetail::find($id);

            if (!$khsDetail) {
                return response()->json([
                    'success' => false,
                    'message' => 'KHS Detail tidak ditemukan.'
                ], 404);
            }

            $khsDetail->delete();

            return response()->json([
                'success' => true,
                'message' => 'KHS Detail berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus KHS Detail.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
