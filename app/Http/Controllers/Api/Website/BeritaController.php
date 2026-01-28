<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use App\Models\Website\Berita;
use Illuminate\Http\Request;
use App\Http\Requests\Website\StoreBeritaRequest;
use App\Http\Requests\Website\UpdateBeritaRequest;
use App\Services\ImageService;
use Illuminate\Support\Facades\Storage;

class BeritaController extends Controller
{
    public function index(Request $request)
    {
        try {
            $berita = Berita::select('id', 'judul', 'gambar', 'isi', 'kategori', 'tanggal')->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar berita',
                'data' => $berita
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data berita',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(StoreBeritaRequest $request, ImageService $imageService)
    {
        try {
            $data = $request->validated();
            if ($request->hasFile('gambar')) {
                $newStoragePath = $imageService->convertToWebpAndReplace($request->file('gambar'), 75, 'berita');
                $data['gambar'] = $newStoragePath;
            }
            $berita = Berita::create($data);
            return response()->json([
                'success' => true,
                'message' => 'Berita berhasil ditambahkan',
                'data' => $berita
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan berita',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $berita = Berita::findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Detail berita',
                'data' => $berita
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Berita tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(UpdateBeritaRequest $request, $id, ImageService $imageService)
    {
        try {
            $berita = Berita::findOrFail($id);
            $data = $request->validated();
            if ($request->hasFile('gambar')) {
                // Hapus gambar lama jika ada
                $oldPath = $berita->gambar ?? null;
                $newStoragePath = $imageService->convertToWebpAndReplace($request->file('gambar'), 75, 'berita', $oldPath);
                $data['gambar'] = $newStoragePath;
            }
            $berita->update($data);
            return response()->json([
                'success' => true,
                'message' => 'Berita berhasil diperbarui',
                'data' => $berita
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui berita',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $berita = Berita::findOrFail($id);
            if ($berita->gambar) {
                Storage::disk('public')->delete($berita->gambar);
            }
            $berita->delete();
            return response()->json([
                'success' => true,
                'message' => 'Berita berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus berita',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
