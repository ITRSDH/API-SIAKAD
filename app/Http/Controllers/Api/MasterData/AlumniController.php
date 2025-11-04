<?php

namespace App\Http\Controllers\Api\MasterData;

use Exception;
use App\Models\MasterData\Mahasiswa;
use Illuminate\Http\Request;
use App\Models\MasterData\Alumni;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;

class AlumniController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $alumnis = Alumni::with(['mahasiswa'])->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar Alumni',
                'data' => $alumnis
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data alumni.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_mahasiswa' => 'required|exists:mahasiswa,id|unique:alumni,id_mahasiswa',
                'tanggal_lulus' => 'required|date',
                'ipk' => 'required|numeric|min:0|max:4',
                'no_ijazah' => 'nullable|string|unique:alumni,no_ijazah',
            ]);

            $alumni = Alumni::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Alumni berhasil ditambahkan.',
                'data' => $alumni
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
                'message' => 'Terjadi kesalahan saat menambahkan alumni.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $alumni = Alumni::with(['mahasiswa'])->find($id);

            if (!$alumni) {
                return response()->json([
                    'success' => false,
                    'message' => 'Alumni tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Alumni',
                'data' => $alumni
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data alumni.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $alumni = Alumni::find($id);

            if (!$alumni) {
                return response()->json([
                    'success' => false,
                    'message' => 'Alumni tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_mahasiswa' => 'sometimes|exists:mahasiswa,id|unique:alumni,id_mahasiswa,' . $id,
                'tanggal_lulus' => 'sometimes|date',
                'ipk' => 'sometimes|numeric|min:0|max:4',
                'no_ijazah' => 'nullable|string|unique:alumni,no_ijazah,' . $id,
            ]);

            $alumni->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Alumni berhasil diperbarui.',
                'data' => $alumni
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
                'message' => 'Terjadi kesalahan saat memperbarui alumni.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $alumni = Alumni::find($id);

            if (!$alumni) {
                return response()->json([
                    'success' => false,
                    'message' => 'Alumni tidak ditemukan.'
                ], 404);
            }

            $alumni->delete();

            return response()->json([
                'success' => true,
                'message' => 'Alumni berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus alumni.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
