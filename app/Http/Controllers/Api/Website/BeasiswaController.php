<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Website\Beasiswa;
use App\Http\Requests\Website\StoreBeasiswaRequest;
use App\Http\Requests\Website\UpdateBeasiswaRequest;
use Illuminate\Support\Facades\Storage;

class BeasiswaController extends Controller
{
    public function index(Request $request)
    {
        try {
            $beasiswa = Beasiswa::select([
                'id', 'nama', 'kategori', 'deskripsi', 'gambar', 'deadline', 'kuota', 'created_at', 'updated_at'
            ])->orderBy('created_at', 'desc')->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar beasiswa',
                'data' => $beasiswa
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data beasiswa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(StoreBeasiswaRequest $request)
    {
        try {
            $data = $request->validated();
            if ($request->hasFile('gambar')) {
                $file = $request->file('gambar');
                $path = $file->store('beasiswa', 'public');
                $data['gambar'] = $path;
            }
            $beasiswa = Beasiswa::create($data);
            return response()->json([
                'success' => true,
                'message' => 'Beasiswa berhasil ditambahkan',
                'data' => $beasiswa
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan beasiswa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $beasiswa = Beasiswa::findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Detail beasiswa',
                'data' => $beasiswa
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Beasiswa tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(UpdateBeasiswaRequest $request, $id)
    {
        try {
            $beasiswa = Beasiswa::findOrFail($id);
            $data = $request->validated();
            if ($request->hasFile('gambar')) {
                // Hapus gambar lama jika ada
                if ($beasiswa->gambar && Storage::disk('public')->exists($beasiswa->gambar)) {
                    Storage::disk('public')->delete($beasiswa->gambar);
                }
                $file = $request->file('gambar');
                $path = $file->store('beasiswa', 'public');
                $data['gambar'] = $path;
            }
            $beasiswa->update($data);
            return response()->json([
                'success' => true,
                'message' => 'Beasiswa berhasil diperbarui',
                'data' => $beasiswa
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui beasiswa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $beasiswa = Beasiswa::findOrFail($id);
            $beasiswa->delete();
            return response()->json([
                'success' => true,
                'message' => 'Beasiswa berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus beasiswa',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
