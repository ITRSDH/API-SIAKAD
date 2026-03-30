<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use App\Models\Website\SertifikatAkreditasi;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SertifikatAkreditasiController extends Controller
{
    public function index(Request $request)
    {
        try {
            $sertifikat = SertifikatAkreditasi::all();
            return response()->json([
                'success' => true,
                'message' => 'Daftar sertifikat akreditasi',
                'data' => $sertifikat
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data sertifikat akreditasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request, ImageService $imageService)
    {
        try {
            $data = $request->validate([
                'nama' => 'required|string|max:255',
                'deskripsi' => 'required|string',
                'foto_sertifikat' => 'required|image|mimes:jpeg,png,jpg,jpeg,webp|max:2048',
            ]);

            if ($request->hasFile('foto_sertifikat')) {
                $newStoragePath = $imageService->convertToWebpAndReplace($request->file('foto_sertifikat'), 75, 'sertifikat_akreditasi');
                $data['foto_sertifikat'] = $newStoragePath;
            }

            $sertifikat = SertifikatAkreditasi::create($data);
            return response()->json([
                'success' => true,
                'message' => 'Sertifikat akreditasi berhasil ditambahkan',
                'data' => $sertifikat
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan sertifikat akreditasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $sertifikat = SertifikatAkreditasi::findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Detail sertifikat akreditasi',
                'data' => $sertifikat
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sertifikat akreditasi tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id, ImageService $imageService)
    {
        try {
            $sertifikat = SertifikatAkreditasi::findOrFail($id);
            $data = $request->validate([
                'nama' => 'sometimes|required|string|max:255',
                'deskripsi' => 'sometimes|required|string',
                'foto_sertifikat' => 'sometimes|required|image|mimes:jpeg,png,jpg,jpeg,webp|max:2048',
            ]);

            if ($request->hasFile('foto_sertifikat')) {
                // Hapus gambar lama jika ada
                $oldPath = $sertifikat->foto_sertifikat ?? null;
                $newStoragePath = $imageService->convertToWebpAndReplace($request->file('foto_sertifikat'), 75, 'sertifikat_akreditasi', $oldPath);
                $data['foto_sertifikat'] = $newStoragePath;
            }

            $sertifikat->update($data);
            return response()->json([
                'success' => true,
                'message' => 'Sertifikat akreditasi berhasil diperbarui',
                'data' => $sertifikat
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui sertifikat akreditasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id, ImageService $imageService)
    {
        try {
            $sertifikat = SertifikatAkreditasi::findOrFail($id);
            
            // Hapus gambar jika ada
            if ($sertifikat->foto_sertifikat && Storage::disk('public')->exists($sertifikat->foto_sertifikat)) {
                $imageService->deletePublicFileIfExists($sertifikat->foto_sertifikat);
            }
            
            $sertifikat->delete();
            return response()->json([
                'success' => true,
                'message' => 'Sertifikat akreditasi berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus sertifikat akreditasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
