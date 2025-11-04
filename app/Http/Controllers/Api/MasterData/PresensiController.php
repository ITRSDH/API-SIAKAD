<?php

namespace App\Http\Controllers\Api\MasterData;

use Exception;
use App\Models\MasterData\KelasMk;
use App\Models\MasterData\Mahasiswa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\MasterData\Presensi;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;

class PresensiController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $presensis = Presensi::with(['kelasMk.mataKuliah', 'mahasiswa'])->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar Presensi',
                'data' => $presensis
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data presensi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_kelas_mk' => 'required|exists:kelas_mk,id',
                'id_mahasiswa' => 'required|exists:mahasiswa,id',
                'tanggal' => 'required|date',
                'status_hadir' => 'required|in:Hadir,Sakit,Izin,Alpha',
                'keterangan' => 'nullable|string',
            ]);

            $presensi = Presensi::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Presensi berhasil ditambahkan.',
                'data' => $presensi
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
                'message' => 'Terjadi kesalahan saat menambahkan presensi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $presensi = Presensi::with(['kelasMk.mataKuliah', 'mahasiswa'])->find($id);

            if (!$presensi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Presensi tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Presensi',
                'data' => $presensi
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data presensi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $presensi = Presensi::find($id);

            if (!$presensi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Presensi tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_kelas_mk' => 'sometimes|exists:kelas_mk,id',
                'id_mahasiswa' => 'sometimes|exists:mahasiswa,id',
                'tanggal' => 'sometimes|date',
                'status_hadir' => 'sometimes|in:Hadir,Sakit,Izin,Alpha',
                'keterangan' => 'nullable|string',
            ]);

            $presensi->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Presensi berhasil diperbarui.',
                'data' => $presensi
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
                'message' => 'Terjadi kesalahan saat memperbarui presensi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $presensi = Presensi::find($id);

            if (!$presensi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Presensi tidak ditemukan.'
                ], 404);
            }

            $presensi->delete();

            return response()->json([
                'success' => true,
                'message' => 'Presensi berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus presensi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
