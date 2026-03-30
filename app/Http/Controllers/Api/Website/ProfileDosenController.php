<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Services\ImageService;
use App\Models\Website\ProfileDosen;
use App\Http\Requests\Website\StoreProfileDosenRequest;
use App\Http\Requests\Website\UpdateProfileDosenRequest;

class ProfileDosenController extends Controller
{
    public function index(Request $request)
    {
        try {
            $profiles = ProfileDosen::with('prodi')->orderBy('created_at', 'desc')->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar profile dosen',
                'data' => $profiles
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data profile dosen',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(StoreProfileDosenRequest $request, ImageService $imageService)
    {
        try {
            $validated = $request->validated();
            $data = $validated;
            $data['id'] = (string) Str::uuid();

            if ($request->hasFile('foto')) {
                $newStoragePath = $imageService->convertToWebpAndReplace($request->file('foto'), 75, 'profile_dosen');
                $data['foto'] = $newStoragePath;
            }

            $profile = ProfileDosen::create($data);
            return response()->json([
                'success' => true,
                'message' => 'Profile dosen berhasil ditambahkan',
                'data' => $profile
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan profile dosen',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $profile = ProfileDosen::with('prodi')->findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Detail profile dosen',
                'data' => $profile
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Profile dosen tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(UpdateProfileDosenRequest $request, $id, ImageService $imageService)
    {
        try {
            $profile = ProfileDosen::findOrFail($id);
            $validated = $request->validated();
            $data = $validated;

            if ($request->hasFile('foto')) {
                $oldPath = $profile->foto ?? null;
                $newStoragePath = $imageService->convertToWebpAndReplace($request->file('foto'), 75, 'profile_dosen', $oldPath);
                $data['foto'] = $newStoragePath;
            }

            $profile->update($data);
            return response()->json([
                'success' => true,
                'message' => 'Profile dosen berhasil diperbarui',
                'data' => $profile
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui profile dosen',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id, ImageService $imageService)
    {
        try {
            $profile = ProfileDosen::findOrFail($id);

            if ($profile->foto && Storage::disk('public')->exists($profile->foto)) {
                $imageService->deletePublicFileIfExists($profile->foto);
            }

            $profile->delete();
            return response()->json([
                'success' => true,
                'message' => 'Profile dosen berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus profile dosen',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
