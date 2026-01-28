<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Website\Prestasi;
use App\Http\Requests\Website\StorePrestasiRequest;
use App\Http\Requests\Website\UpdatePrestasiRequest;
use App\Services\ImageService;
use Illuminate\Support\Facades\Storage;

class PrestasiController extends Controller
{
    public function index(Request $request)
    {
        try {
            $prestasi = Prestasi::select([
                'id',
                'nama_mahasiswa',
                'judul_prestasi',
                'tingkat',
                'tahun',
                'deskripsi',
                'gambar',
                'id_prodi', // ← PENTING untuk relasi
                'created_at',
                'updated_at',
            ])
                ->with('prodi:id,nama_prodi,id_jenjang_pendidikan') // ← Specify kolom yg diperlukan
                ->orderBy('created_at', 'desc')->get();

            return response()->json(
                [
                    'success' => true,
                    'message' => 'Daftar prestasi',
                    'data' => $prestasi,
                ],
                200,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Gagal mengambil data prestasi',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function store(StorePrestasiRequest $request, ImageService $imageService)
    {
        try {
            $data = $request->validated();
            if ($request->hasFile('gambar')) {
                $newStoragePath = $imageService->convertToWebpAndReplace($request->file('gambar'), 75, 'prestasi');
                $data['gambar'] = $newStoragePath;
            }
            $prestasi = Prestasi::create($data);
            return response()->json(
                [
                    'success' => true,
                    'message' => 'Prestasi berhasil ditambahkan',
                    'data' => $prestasi,
                ],
                201,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Gagal menambahkan prestasi',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function show($id)
    {
        try {
            $prestasi = Prestasi::findOrFail($id);
            return response()->json(
                [
                    'success' => true,
                    'message' => 'Detail prestasi',
                    'data' => $prestasi,
                ],
                200,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Prestasi tidak ditemukan',
                    'error' => $e->getMessage(),
                ],
                404,
            );
        }
    }

    public function update(UpdatePrestasiRequest $request, $id, ImageService $imageService)
    {
        try {
            $prestasi = Prestasi::with('prodi:id,nama_prodi,id_jenjang_pendidikan')->findOrFail($id);
            $data = $request->validated();
            if ($request->hasFile('gambar')) {
                // Hapus gambar lama jika ada
                $oldPath = $prestasi->gambar ?? null;
                $newStoragePath = $imageService->convertToWebpAndReplace($request->file('gambar'), 75, 'prestasi', $oldPath);
                $data['gambar'] = $newStoragePath;
            }
            $prestasi->update($data);
            return response()->json(
                [
                    'success' => true,
                    'message' => 'Prestasi berhasil diperbarui',
                    'data' => $prestasi,
                ],
                200,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Gagal memperbarui prestasi',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function destroy($id)
    {
        try {
            $prestasi = Prestasi::findOrFail($id);
            if ($prestasi->gambar) {
                Storage::disk('public')->delete($prestasi->gambar);
            }
            $prestasi->delete();
            return response()->json(
                [
                    'success' => true,
                    'message' => 'Prestasi berhasil dihapus',
                ],
                200,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Gagal menghapus prestasi',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }
}
