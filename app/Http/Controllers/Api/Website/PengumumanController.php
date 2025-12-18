<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Website\StorePengumumanRequest;
use App\Http\Requests\Website\UpdatePengumumanRequest;
use App\Models\Website\Pengumuman;

class PengumumanController extends Controller
{
    public function index(Request $request)
    {
        try {
            $search = $request->query('search');
            $perPage = $request->query('per_page', 10);

            $query = Pengumuman::select('id', 'judul', 'isi', 'kategori', 'tanggal')
                ->orderBy('created_at', 'desc');

            // Apply search filter jika ada parameter search
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('judul', 'like', "%{$search}%")
                        ->orWhere('isi', 'like', "%{$search}%")
                        ->orWhere('kategori', 'like', "%{$search}%");
                });
            }

            $pengumuman = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Daftar pengumuman',
                'data' => $pengumuman
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data pengumuman',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(StorePengumumanRequest $request)
    {
        try {
            $pengumuman = Pengumuman::create($request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Pengumuman berhasil ditambahkan',
                'data' => $pengumuman
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan pengumuman',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $pengumuman = Pengumuman::findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Detail pengumuman',
                'data' => $pengumuman
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pengumuman tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(UpdatePengumumanRequest $request, $id)
    {
        try {
            $pengumuman = Pengumuman::findOrFail($id);
            $pengumuman->update($request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Pengumuman berhasil diperbarui',
                'data' => $pengumuman
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui pengumuman',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $pengumuman = Pengumuman::findOrFail($id);
            $pengumuman->delete();
            return response()->json([
                'success' => true,
                'message' => 'Pengumuman berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus pengumuman',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
