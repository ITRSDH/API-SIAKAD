<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use App\Models\Website\Berita;
use Illuminate\Http\Request;
use App\Http\Requests\Website\StoreBeritaRequest;
use App\Http\Requests\Website\UpdateBeritaRequest;
use Illuminate\Support\Facades\Storage;

class BeritaController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Ambil semua data, tapi hanya kolom tertentu yang ditampilkan
            $berita = Berita::select('id', 'judul', 'isi', 'kategori', 'created_at')->orderBy('created_at', 'desc')->get();

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

    public function store(StoreBeritaRequest $request)
    {
        try {
            $data = $request->validated();
            if ($request->hasFile('gambar')) {
                $file = $request->file('gambar');
                $path = $file->store('berita', 'public');
                $data['gambar'] = $path;
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

    public function update(UpdateBeritaRequest $request, $id)
    {
        try {
            $berita = Berita::findOrFail($id);
            $data = $request->validated();
            if ($request->hasFile('gambar')) {
                // Hapus gambar lama jika ada
                if ($berita->gambar && Storage::disk('public')->exists($berita->gambar)) {
                    Storage::disk('public')->delete($berita->gambar);
                }
                $file = $request->file('gambar');
                $path = $file->store('berita', 'public');
                $data['gambar'] = $path;
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
