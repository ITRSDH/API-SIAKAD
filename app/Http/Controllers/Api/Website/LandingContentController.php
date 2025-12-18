<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Website\StoreLandingContentRequest;
use App\Http\Requests\Website\UpdateLandingContentRequest;
use Illuminate\Support\Facades\Storage;

use App\Models\Website\LandingContent;
use App\Services\ImageService;

class LandingContentController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Ambil data pertama (single content)
            $content = LandingContent::select(['id', 'hero_title', 'hero_subtitle', 'hero_background', 'jumlah_program_studi', 'jumlah_mahasiswa', 'jumlah_dosen', 'jumlah_mitra', 'keunggulan', 'logo', 'nama_aplikasi', 'deskripsi_footer', 'facebook', 'twitter', 'instagram', 'linkedin', 'youtube', 'alamat', 'telepon', 'email', 'created_at', 'updated_at'])->first();
            return response()->json(
                [
                    'success' => true,
                    'message' => 'Data landing content',
                    'data' => $content,
                ],
                200,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Gagal mengambil data landing content',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function store(StoreLandingContentRequest $request, ImageService $imageService)
    {
        try {
            $data = $request->validated();
            // Cek apakah sudah ada content
            $existing = LandingContent::first();

            if ($existing) {
                // Update existing
                if ($request->hasFile('logo')) {
                    $oldPath = $existing?->logo ?? null;

                    // convert, simpan, dan hapus yang lama
                    $newStoragePath = $imageService->convertToWebpAndReplace($request->file('logo'), 75, 'landing/logo', $oldPath);

                    // Update data untuk DB
                    $data['logo'] = $newStoragePath;
                }

                if ($request->hasFile('hero_background')) {
                    $oldPath = $existing?->hero_background ?? null;

                    // convert, simpan, dan hapus yang lama
                    $newStoragePath = $imageService->convertToWebpAndReplace($request->file('hero_background'), 75, 'landing/hero', $oldPath);
                    $data['hero_background'] = $newStoragePath;
                }

                $existing->update($data);
                return response()->json(
                    [
                        'success' => true,
                        'message' => 'Landing content berhasil diperbarui',
                        'data' => $existing,
                    ],
                    200,
                );
            }

            // Create new
            if ($request->hasFile('logo')) {
                $newStoragePath = $imageService->convertToWebpAndReplace($request->file('logo'), 75, 'landing/logo');
                $data['logo'] = $newStoragePath;
            }

            if ($request->hasFile('hero_background')) {
                $newStoragePath = $imageService->convertToWebpAndReplace($request->file('hero_background'), 75, 'landing/hero');
                $data['hero_background'] = $newStoragePath;
            }

            $content = LandingContent::create($data);
            return response()->json(
                [
                    'success' => true,
                    'message' => 'Landing content berhasil ditambahkan',
                    'data' => $content,
                ],
                201,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Gagal menambahkan landing content',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function show($id)
    {
        try {
            // Ambil data pertama (ignore ID)
            $content = LandingContent::first();
            if (!$content) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'Landing content belum dibuat',
                    ],
                    404,
                );
            }
            return response()->json(
                [
                    'success' => true,
                    'message' => 'Detail landing content',
                    'data' => $content,
                ],
                200,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Gagal mengambil detail landing content',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function update(UpdateLandingContentRequest $request, $id)
    {
        try {
            // Karena single content, ambil pertama
            $content = LandingContent::first();
            if (!$content) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'Landing content belum dibuat. Gunakan endpoint store untuk membuat data baru.',
                    ],
                    404,
                );
            }

            $data = $request->validated();

            if ($request->hasFile('logo')) {
                if ($content->logo && Storage::disk('public')->exists($content->logo)) {
                    Storage::disk('public')->delete($content->logo);
                }
                $file = $request->file('logo');
                $path = $file->store('landing/logo', 'public');
                $data['logo'] = $path;
            }

            if ($request->hasFile('hero_background')) {
                if ($content->hero_background && Storage::disk('public')->exists($content->hero_background)) {
                    Storage::disk('public')->delete($content->hero_background);
                }
                $file = $request->file('hero_background');
                $path = $file->store('landing/hero', 'public');
                $data['hero_background'] = $path;
            }

            $content->update($data);
            return response()->json(
                [
                    'success' => true,
                    'message' => 'Landing content berhasil diperbarui',
                    'data' => $content,
                ],
                200,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Gagal memperbarui landing content',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function destroy($id)
    {
        try {
            // Ambil single content
            $content = LandingContent::first();
            if (!$content) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'Landing content tidak ditemukan',
                    ],
                    404,
                );
            }

            // Hapus files jika ada
            if ($content->logo && Storage::disk('public')->exists($content->logo)) {
                Storage::disk('public')->delete($content->logo);
            }
            if ($content->hero_background && Storage::disk('public')->exists($content->hero_background)) {
                Storage::disk('public')->delete($content->hero_background);
            }

            $content->delete();
            return response()->json(
                [
                    'success' => true,
                    'message' => 'Landing content berhasil dihapus',
                ],
                200,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Gagal menghapus landing content',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }
}
