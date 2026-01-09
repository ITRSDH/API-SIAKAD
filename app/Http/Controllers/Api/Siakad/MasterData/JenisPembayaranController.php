<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\MasterData\JenisPembayaran;
use Illuminate\Validation\ValidationException;

class JenisPembayaranController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $jenisPembayarans = JenisPembayaran::all();
            return response()->json([
                'success' => true,
                'message' => 'Daftar Jenis Pembayaran',
                'data' => $jenisPembayarans
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data jenis pembayaran.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'nama_pembayaran' => 'required|string|max:255',
                'nominal' => 'required|integer|min:0', // Dalam satuan rupiah
                'keterangan' => 'nullable|string',
            ]);

            $jenisPembayaran = JenisPembayaran::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Jenis Pembayaran berhasil ditambahkan.',
                'data' => $jenisPembayaran
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
                'message' => 'Terjadi kesalahan saat menambahkan jenis pembayaran.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $jenisPembayaran = JenisPembayaran::find($id);

            if (!$jenisPembayaran) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis Pembayaran tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Jenis Pembayaran',
                'data' => $jenisPembayaran
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data jenis pembayaran.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $jenisPembayaran = JenisPembayaran::find($id);

            if (!$jenisPembayaran) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis Pembayaran tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'nama_pembayaran' => 'sometimes|string|max:255',
                'nominal' => 'sometimes|integer|min:0',
                'keterangan' => 'nullable|string',
            ]);

            $jenisPembayaran->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Jenis Pembayaran berhasil diperbarui.',
                'data' => $jenisPembayaran
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
                'message' => 'Terjadi kesalahan saat memperbarui jenis pembayaran.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $jenisPembayaran = JenisPembayaran::find($id);

            if (!$jenisPembayaran) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis Pembayaran tidak ditemukan.'
                ], 404);
            }

            $jenisPembayaran->delete();

            return response()->json([
                'success' => true,
                'message' => 'Jenis Pembayaran berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus jenis pembayaran.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
