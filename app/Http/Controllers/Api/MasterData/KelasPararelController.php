<?php

namespace App\Http\Controllers\Api\MasterData;

use Exception;
use Illuminate\Http\Request;
use App\Models\MasterData\Prodi;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\MasterData\KelasPararel;
use Illuminate\Validation\ValidationException;

class KelasPararelController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $kelasPararels = KelasPararel::with(['prodi'])->get();
            $prodi = Prodi::get();

            return response()->json([
                'success' => true,
                'message' => 'Data All Kelas Pararel berhasil diambil',
                'data' => [
                    'kelas-pararel' => $kelasPararels,
                    'prodi' => $prodi,
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data All Kelas Pararel.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAllData(): JsonResponse
    {
        try {
            $kelasPararels = KelasPararel::with(['prodi'])->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar Kelas Pararel',
                'data' => $kelasPararels
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data kelas pararel.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_prodi' => 'required|exists:prodi,id',
                'nama_kelas' => 'required|string|max:255',
                'angkatan' => 'required|integer|min:2000|max:' . (date('Y') + 10),
            ]);

            $kelasPararel = KelasPararel::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Kelas Pararel berhasil ditambahkan.',
                'data' => $kelasPararel
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
                'message' => 'Terjadi kesalahan saat menambahkan kelas pararel.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $kelasPararel = KelasPararel::with(['prodi'])->find($id);

            if (!$kelasPararel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas Pararel tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Kelas Pararel',
                'data' => $kelasPararel
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data kelas pararel.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $kelasPararel = KelasPararel::find($id);

            if (!$kelasPararel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas Pararel tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_prodi' => 'sometimes|exists:prodi,id',
                'nama_kelas' => 'sometimes|string|max:255',
                'angkatan' => 'sometimes|integer|min:2000|max:' . (date('Y') + 10),
            ]);

            $kelasPararel->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Kelas Pararel berhasil diperbarui.',
                'data' => $kelasPararel
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
                'message' => 'Terjadi kesalahan saat memperbarui kelas pararel.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $kelasPararel = KelasPararel::find($id);

            if (!$kelasPararel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas Pararel tidak ditemukan.'
                ], 404);
            }

            $kelasPararel->delete();

            return response()->json([
                'success' => true,
                'message' => 'Kelas Pararel berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus kelas pararel.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
