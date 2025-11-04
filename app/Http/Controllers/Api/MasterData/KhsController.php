<?php

namespace App\Http\Controllers\Api\MasterData;

use Exception;
use App\Models\MasterData\Semester;
use App\Models\MasterData\Mahasiswa;
use Illuminate\Http\Request;
use App\Models\MasterData\Khs;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;

class KhsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $khss = Khs::with(['mahasiswa', 'semester'])->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar KHS',
                'data' => $khss
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data KHS.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_mahasiswa' => 'required|exists:mahasiswa,id',
                'id_semester' => 'required|exists:semester,id',
                'ip_semester' => 'required|numeric|min:0|max:4',
                'total_sks_semester' => 'required|integer|min:0',
                'ip_kumulatif' => 'required|numeric|min:0|max:4',
            ]);

            $khs = Khs::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'KHS berhasil ditambahkan.',
                'data' => $khs
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
                'message' => 'Terjadi kesalahan saat menambahkan KHS.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $khs = Khs::with(['mahasiswa', 'semester', 'khsDetail.mataKuliah'])->find($id);

            if (!$khs) {
                return response()->json([
                    'success' => false,
                    'message' => 'KHS tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail KHS',
                'data' => $khs
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data KHS.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $khs = Khs::find($id);

            if (!$khs) {
                return response()->json([
                    'success' => false,
                    'message' => 'KHS tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_mahasiswa' => 'sometimes|exists:mahasiswa,id',
                'id_semester' => 'sometimes|exists:semester,id',
                'ip_semester' => 'sometimes|numeric|min:0|max:4',
                'total_sks_semester' => 'sometimes|integer|min:0',
                'ip_kumulatif' => 'sometimes|numeric|min:0|max:4',
            ]);

            $khs->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'KHS berhasil diperbarui.',
                'data' => $khs
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
                'message' => 'Terjadi kesalahan saat memperbarui KHS.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $khs = Khs::find($id);

            if (!$khs) {
                return response()->json([
                    'success' => false,
                    'message' => 'KHS tidak ditemukan.'
                ], 404);
            }

            $khs->delete();

            return response()->json([
                'success' => true,
                'message' => 'KHS berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus KHS.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
