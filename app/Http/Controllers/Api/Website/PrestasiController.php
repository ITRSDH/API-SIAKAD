<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Website\Prestasi;
use App\Http\Requests\Website\StorePrestasiRequest;
use App\Http\Requests\Website\UpdatePrestasiRequest;
use Illuminate\Support\Facades\Storage;

class PrestasiController extends Controller
{
    public function index(Request $request)
    {
        try {
            $prestasi = Prestasi::select([
                'id', 'nama_mahasiswa', 'program_studi', 'judul_prestasi', 'tingkat', 'tahun', 'deskripsi', 'gambar', 'created_at', 'updated_at'
            ])->orderBy('created_at', 'desc')->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar prestasi',
                'data' => $prestasi
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data prestasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(StorePrestasiRequest $request)
    {
        try {
            $data = $request->validated();
            if ($request->hasFile('gambar')) {
                $file = $request->file('gambar');
                $path = $file->store('prestasi', 'public');
                $data['gambar'] = $path;
            }
            $prestasi = Prestasi::create($data);
            return response()->json([
                'success' => true,
                'message' => 'Prestasi berhasil ditambahkan',
                'data' => $prestasi
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan prestasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $prestasi = Prestasi::findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Detail prestasi',
                'data' => $prestasi
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Prestasi tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(UpdatePrestasiRequest $request, $id)
    {
        try {
            $prestasi = Prestasi::findOrFail($id);
            $data = $request->validated();
            if ($request->hasFile('gambar')) {
                // Hapus gambar lama jika ada
                if ($prestasi->gambar && Storage::disk('public')->exists($prestasi->gambar)) {
                    Storage::disk('public')->delete($prestasi->gambar);
                }
                $file = $request->file('gambar');
                $path = $file->store('prestasi', 'public');
                $data['gambar'] = $path;
            }
            $prestasi->update($data);
            return response()->json([
                'success' => true,
                'message' => 'Prestasi berhasil diperbarui',
                'data' => $prestasi
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui prestasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $prestasi = Prestasi::findOrFail($id);
            $prestasi->delete();
            return response()->json([
                'success' => true,
                'message' => 'Prestasi berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus prestasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
