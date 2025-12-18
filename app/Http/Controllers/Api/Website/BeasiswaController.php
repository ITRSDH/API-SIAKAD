<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Website\Beasiswa;
use App\Http\Requests\Website\StoreBeasiswaRequest;
use App\Http\Requests\Website\UpdateBeasiswaRequest;
use App\Services\ImageService;
use Illuminate\Support\Facades\Storage;
use Nette\Utils\Image;

class BeasiswaController extends Controller
{
    public function index(Request $request)
    {
        try {
            $search = $request->query('search');
            $perPage = $request->query('per_page', 10);

            $query = Beasiswa::select([
                'id', 'nama', 'kategori', 'deskripsi', 'gambar', 'deadline', 'kuota', 'created_at', 'updated_at'
            ])->orderBy('created_at', 'desc');

            // Apply search filter jika ada parameter search
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                        ->orWhere('kategori', 'like', "%{$search}%")
                        ->orWhere('deskripsi', 'like', "%{$search}%");
                });
            }

            $beasiswa = $query->paginate($perPage);

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

    public function store(StoreBeasiswaRequest $request, ImageService $imageService)
    {
        try {
            $data = $request->validated();
            if ($request->hasFile('gambar')) {
                $newStoragePath = $imageService->convertToWebpAndReplace($request->file('gambar'), 75, 'beasiswa');
                $data['gambar'] = $newStoragePath;
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

    public function update(UpdateBeasiswaRequest $request, $id, ImageService $imageService)
    {
        try {
            $beasiswa = Beasiswa::findOrFail($id);
            $data = $request->validated();
            if ($request->hasFile('gambar')) {
                // Hapus gambar lama jika ada
                $oldPath = $beasiswa->gambar ?? null;
                $newStoragePath = $imageService->convertToWebpAndReplace($request->file('gambar'), 75, 'beasiswa', $oldPath);
                $data['gambar'] = $newStoragePath;
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
            if ($beasiswa->gambar) {
                Storage::disk('public')->delete($beasiswa->gambar);
            }
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
