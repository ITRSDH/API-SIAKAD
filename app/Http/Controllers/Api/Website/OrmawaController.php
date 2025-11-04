<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Website\Ormawa;
use App\Http\Requests\Website\StoreOrmawaRequest;
use App\Http\Requests\Website\UpdateOrmawaRequest;
use Illuminate\Support\Facades\Storage;

class OrmawaController extends Controller
{
    public function index(Request $request)
    {
        try {
            $ormawa = Ormawa::select([
                'id', 'nama', 'deskripsi', 'gambar', 'created_at', 'updated_at'
            ])->orderBy('created_at', 'desc')->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar ormawa',
                'data' => $ormawa
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data ormawa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(StoreOrmawaRequest $request)
    {
        try {
            $data = $request->validated();

            if ($request->hasFile('gambar')) {
                $file = $request->file('gambar');
                $path = $file->store('ormawa', 'public');
                $data['gambar'] = $path;
            }

            $ormawa = Ormawa::create($data);
            return response()->json([
                'success' => true,
                'message' => 'Ormawa berhasil ditambahkan',
                'data' => $ormawa
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan ormawa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $ormawa = Ormawa::findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Detail ormawa',
                'data' => $ormawa
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ormawa tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(UpdateOrmawaRequest $request, $id)
    {
        try {
            $ormawa = Ormawa::findOrFail($id);
            $data = $request->validated();

            if ($request->hasFile('gambar')) {
                // Hapus gambar lama jika ada
                if ($ormawa->gambar && Storage::disk('public')->exists($ormawa->gambar)) {
                    Storage::disk('public')->delete($ormawa->gambar);
                }
                $file = $request->file('gambar');
                $path = $file->store('ormawa', 'public');
                $data['gambar'] = $path;
            }

            $ormawa->update($data);
            return response()->json([
                'success' => true,
                'message' => 'Ormawa berhasil diperbarui',
                'data' => $ormawa
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui ormawa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $ormawa = Ormawa::findOrFail($id);
            $ormawa->delete();
            return response()->json([
                'success' => true,
                'message' => 'Ormawa berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus ormawa',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
