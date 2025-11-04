<?php

namespace App\Http\Controllers\Api\MasterData;

use Exception;
use App\Models\MasterData\Mahasiswa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Models\MasterData\StatusAkademikMahasiswa;

class StatusAkademikMahasiswaController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $statusAkademikMahasiswas = StatusAkademikMahasiswa::with(['mahasiswa'])->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar Status Akademik Mahasiswa',
                'data' => $statusAkademikMahasiswas
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data status akademik mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_mahasiswa' => 'required|exists:mahasiswa,id',
                'status_baru' => 'required|in:Aktif,Cuti,DO,Lulus',
                'tanggal_ubah' => 'required|date',
                'keterangan' => 'nullable|string',
            ]);

            $statusAkademikMahasiswa = StatusAkademikMahasiswa::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Status Akademik Mahasiswa berhasil ditambahkan.',
                'data' => $statusAkademikMahasiswa
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
                'message' => 'Terjadi kesalahan saat menambahkan status akademik mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $statusAkademikMahasiswa = StatusAkademikMahasiswa::with(['mahasiswa'])->find($id);

            if (!$statusAkademikMahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Status Akademik Mahasiswa tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Status Akademik Mahasiswa',
                'data' => $statusAkademikMahasiswa
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data status akademik mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $statusAkademikMahasiswa = StatusAkademikMahasiswa::find($id);

            if (!$statusAkademikMahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Status Akademik Mahasiswa tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_mahasiswa' => 'sometimes|exists:mahasiswa,id',
                'status_baru' => 'sometimes|in:Aktif,Cuti,DO,Lulus',
                'tanggal_ubah' => 'sometimes|date',
                'keterangan' => 'nullable|string',
            ]);

            $statusAkademikMahasiswa->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Status Akademik Mahasiswa berhasil diperbarui.',
                'data' => $statusAkademikMahasiswa
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
                'message' => 'Terjadi kesalahan saat memperbarui status akademik mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $statusAkademikMahasiswa = StatusAkademikMahasiswa::find($id);

            if (!$statusAkademikMahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Status Akademik Mahasiswa tidak ditemukan.'
                ], 404);
            }

            $statusAkademikMahasiswa->delete();

            return response()->json([
                'success' => true,
                'message' => 'Status Akademik Mahasiswa berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus status akademik mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
