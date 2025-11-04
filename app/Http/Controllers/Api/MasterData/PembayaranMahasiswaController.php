<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\PembayaranMahasiswa;
use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\JenisPembayaran;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PembayaranMahasiswaController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $pembayaranMahasiswas = PembayaranMahasiswa::with(['mahasiswa', 'jenisPembayaran'])->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar Pembayaran Mahasiswa',
                'data' => $pembayaranMahasiswas
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data pembayaran mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_mahasiswa' => 'required|exists:mahasiswa,id',
                'id_jenis_pembayaran' => 'required|exists:jenis_pembayaran,id',
                'tanggal_bayar' => 'required|date',
                'jumlah_bayar' => 'required|integer|min:0', // Dalam satuan rupiah
                'status_pembayaran' => 'required|in:Lunas,Belum Lunas,Dibatalkan',
                'keterangan' => 'nullable|string',
            ]);

            $pembayaranMahasiswa = PembayaranMahasiswa::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Pembayaran Mahasiswa berhasil ditambahkan.',
                'data' => $pembayaranMahasiswa
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
                'message' => 'Terjadi kesalahan saat menambahkan pembayaran mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $pembayaranMahasiswa = PembayaranMahasiswa::with(['mahasiswa', 'jenisPembayaran'])->find($id);

            if (!$pembayaranMahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pembayaran Mahasiswa tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Pembayaran Mahasiswa',
                'data' => $pembayaranMahasiswa
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data pembayaran mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $pembayaranMahasiswa = PembayaranMahasiswa::find($id);

            if (!$pembayaranMahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pembayaran Mahasiswa tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_mahasiswa' => 'sometimes|exists:mahasiswa,id',
                'id_jenis_pembayaran' => 'sometimes|exists:jenis_pembayaran,id',
                'tanggal_bayar' => 'sometimes|date',
                'jumlah_bayar' => 'sometimes|integer|min:0',
                'status_pembayaran' => 'sometimes|in:Lunas,Belum Lunas,Dibatalkan',
                'keterangan' => 'nullable|string',
            ]);

            $pembayaranMahasiswa->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Pembayaran Mahasiswa berhasil diperbarui.',
                'data' => $pembayaranMahasiswa
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
                'message' => 'Terjadi kesalahan saat memperbarui pembayaran mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $pembayaranMahasiswa = PembayaranMahasiswa::find($id);

            if (!$pembayaranMahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pembayaran Mahasiswa tidak ditemukan.'
                ], 404);
            }

            $pembayaranMahasiswa->delete();

            return response()->json([
                'success' => true,
                'message' => 'Pembayaran Mahasiswa berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus pembayaran mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
