<?php

namespace App\Http\Controllers\Api\MasterData;

use Exception;
use Illuminate\Http\Request;
use App\Models\MasterData\Ruang;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;

class RuangController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $ruang = Ruang::all();
            return response()->json([
                'success' => true,
                'message' => 'Daftar Ruang',
                'data' => $ruang
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data ruang.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'nama_ruang' => 'required|string|max:255',
                'kapasitas' => 'required|integer|min:1',
                'jenis_ruang' => 'nullable|string|max:255',
            ]);

            $ruang = Ruang::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Ruang berhasil ditambahkan.',
                'data' => $ruang
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
                'message' => 'Terjadi kesalahan saat menambahkan ruang.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $ruang = Ruang::find($id);

            if (!$ruang) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ruang tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Ruang',
                'data' => $ruang
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data ruang.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $ruang = Ruang::find($id);

            if (!$ruang) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ruang tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'nama_ruang' => 'sometimes|string|max:255',
                'kapasitas' => 'sometimes|integer|min:1',
                'jenis_ruang' => 'nullable|string|max:255',
            ]);

            $ruang->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Ruang berhasil diperbarui.',
                'data' => $ruang
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
                'message' => 'Terjadi kesalahan saat memperbarui ruang.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $ruang = Ruang::find($id);

            if (!$ruang) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ruang tidak ditemukan.'
                ], 404);
            }

            $ruang->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ruang berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus ruang.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
