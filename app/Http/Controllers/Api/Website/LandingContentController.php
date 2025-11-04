<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Website\StoreLandingContentRequest;
use App\Http\Requests\Website\UpdateLandingContentRequest;
use Illuminate\Support\Facades\Storage;

use App\Models\Website\LandingContent;

class LandingContentController extends Controller
{
    public function index(Request $request)
    {
        try {
            $contents = LandingContent::select([
                'id',
                'hero_title', 'hero_subtitle', 'hero_background',
                'jumlah_program_studi', 'jumlah_mahasiswa', 'jumlah_dosen', 'jumlah_mitra',
                'keunggulan',
                'logo', 'nama_aplikasi',
                'deskripsi_footer', 'facebook', 'twitter', 'instagram', 'linkedin', 'youtube',
                'alamat', 'telepon', 'email',
                'created_at', 'updated_at'
            ])->orderBy('created_at', 'desc')->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar landing content',
                'data' => $contents
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data landing content',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(StoreLandingContentRequest $request)
    {
        try {
            $data = $request->validated();

            if ($request->hasFile('logo')) {
                $file = $request->file('logo');
                $path = $file->store('landing/logo', 'public');
                $data['logo'] = $path;
            }

            if ($request->hasFile('hero_background')) {
                $file = $request->file('hero_background');
                $path = $file->store('landing/hero', 'public');
                $data['hero_background'] = $path;
            }

            $content = LandingContent::create($data);
            return response()->json([
                'success' => true,
                'message' => 'Landing content berhasil ditambahkan',
                'data' => $content
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan landing content',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $content = LandingContent::findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Detail landing content',
                'data' => $content
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Landing content tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(UpdateLandingContentRequest $request, $id)
    {
        try {
            $content = LandingContent::findOrFail($id);
            $data = $request->validated();

            if ($request->hasFile('logo')) {
                // Hapus logo lama jika ada
                if ($content->logo && Storage::disk('public')->exists($content->logo)) {
                    Storage::disk('public')->delete($content->logo);
                }
                $file = $request->file('logo');
                $path = $file->store('landing/logo', 'public');
                $data['logo'] = $path;
            }

            if ($request->hasFile('hero_background')) {
                // Hapus hero_background lama jika ada
                if ($content->hero_background && Storage::disk('public')->exists($content->hero_background)) {
                    Storage::disk('public')->delete($content->hero_background);
                }
                $file = $request->file('hero_background');
                $path = $file->store('landing/hero', 'public');
                $data['hero_background'] = $path;
            }

            $content->update($data);
            return response()->json([
                'success' => true,
                'message' => 'Landing content berhasil diperbarui',
                'data' => $content
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui landing content',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $content = LandingContent::findOrFail($id);
            $content->delete();
            return response()->json([
                'success' => true,
                'message' => 'Landing content berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus landing content',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
