<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\Kurikulum;
use App\Models\MasterData\Prodi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class KurikulumController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $kurikulum = Kurikulum::with(['prodi'])->get();
            $prodi = Prodi::get();

            return response()->json([
                'success' => true,
                'message' => 'Data All Kurikulum berhasil diambil',
                'data' => [
                    'kurikulum' => $kurikulum,
                    'prodi' => $prodi,
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data All Kurikulum.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAllData(): JsonResponse
    {
        try {
            $kurikulums = Kurikulum::with(['prodi'])->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar Kurikulum',
                'data' => $kurikulums
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data kurikulum.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_prodi' => 'required|exists:prodi,id',
                'nama_kurikulum' => 'required|string|max:255',
                'tahun_kurikulum' => 'required|integer|min:2000|max:' . (date('Y') + 10),
                'status' => 'boolean',
            ]);

            $kurikulum = Kurikulum::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Kurikulum berhasil ditambahkan.',
                'data' => $kurikulum
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
                'message' => 'Terjadi kesalahan saat menambahkan kurikulum.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $kurikulum = Kurikulum::with(['prodi'])->find($id);

            if (!$kurikulum) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kurikulum tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Kurikulum',
                'data' => $kurikulum
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data kurikulum.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $kurikulum = Kurikulum::find($id);

            if (!$kurikulum) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kurikulum tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_prodi' => 'sometimes|exists:prodi,id',
                'nama_kurikulum' => 'sometimes|string|max:255',
                'tahun_kurikulum' => 'sometimes|integer|min:2000|max:' . (date('Y') + 10),
                'status' => 'sometimes|boolean',
            ]);

            $kurikulum->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Kurikulum berhasil diperbarui.',
                'data' => $kurikulum
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
                'message' => 'Terjadi kesalahan saat memperbarui kurikulum.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $kurikulum = Kurikulum::find($id);

            if (!$kurikulum) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kurikulum tidak ditemukan.'
                ], 404);
            }

            $kurikulum->delete();

            return response()->json([
                'success' => true,
                'message' => 'Kurikulum berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus kurikulum.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
