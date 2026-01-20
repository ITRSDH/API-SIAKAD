<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Website\ProfileKampus;
use App\Http\Requests\Website\StoreProfileKampusRequest;
use App\Http\Requests\Website\UpdateProfileKampusRequest;
use App\Services\ImageService;
use Illuminate\Support\Facades\Storage;

class ProfileKampusController extends Controller
{
	public function index(Request $request)
	{
		try {
			// Ambil data pertama (hanya ada satu data)
			$profile = ProfileKampus::select([
				'id', 'judul', 'deskripsi', 'visi', 'misi', 'struktur_image', 'fasilitas', 'created_at', 'updated_at'
			])->first();
			
			return response()->json([
				'success' => true,
				'message' => 'Data profile kampus',
				'data' => $profile
			], 200);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Gagal mengambil data profile kampus',
				'error' => $e->getMessage()
			], 500);
		}
	}

	public function store(StoreProfileKampusRequest $request, ImageService $imageService)
	{
		try {
			$data = $request->validated();
			
			// Cek apakah sudah ada data profile kampus
			$existingProfile = ProfileKampus::first();
			
			if ($existingProfile) {
				// Jika sudah ada, lakukan update
				if ($request->hasFile('struktur_image')) {
					$oldPath = $existingProfile->struktur_image ?? null;
					$newStoragePath = $imageService->convertToWebpAndReplace($request->file('struktur_image'), 75, 'profile_kampus', $oldPath);
					$data['struktur_image'] = $newStoragePath;
				}
				
				$existingProfile->update($data);
				
				return response()->json([
					'success' => true,
					'message' => 'Profile kampus berhasil diperbarui',
					'data' => $existingProfile
				], 200);
			} else {
				// Jika belum ada, buat data baru
				if ($request->hasFile('struktur_image')) {
					$file = $request->file('struktur_image');
					$newStoragePath = $imageService->convertToWebpAndReplace($file, 75, 'profile_kampus');
					$data['struktur_image'] = $newStoragePath;
				}
				
				$profile = ProfileKampus::create($data);
				
				return response()->json([
					'success' => true,
					'message' => 'Profile kampus berhasil ditambahkan',
					'data' => $profile
				], 201);
			}
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Gagal menyimpan profile kampus',
				'error' => $e->getMessage()
			], 500);
		}
	}

	public function show($id = null)
	{
		try {
			// Karena hanya ada satu data, ambil data pertama (ignore ID)
			$profile = ProfileKampus::first();
			
			if (!$profile) {
				return response()->json([
					'success' => false,
					'message' => 'Profile kampus belum dibuat'
				], 404);
			}
			
			return response()->json([
				'success' => true,
				'message' => 'Detail profile kampus',
				'data' => $profile
			], 200);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Gagal mengambil detail profile kampus',
				'error' => $e->getMessage()
			], 500);
		}
	}

	public function update(UpdateProfileKampusRequest $request, $id = null)
	{
		try {
			// Karena hanya ada satu data, ambil data pertama (ignore ID)
			$profile = ProfileKampus::first();
			
			if (!$profile) {
				return response()->json([
					'success' => false,
					'message' => 'Profile kampus belum dibuat. Gunakan endpoint store untuk membuat data baru.'
				], 404);
			}
			
			$data = $request->validated();
			if ($request->hasFile('struktur_image')) {
				// Hapus gambar lama jika ada
				if ($profile->struktur_image && Storage::disk('public')->exists($profile->struktur_image)) {
					Storage::disk('public')->delete($profile->struktur_image);
				}
				$file = $request->file('struktur_image');
				$path = $file->store('profile_kampus', 'public');
				$data['struktur_image'] = $path;
			}
			$profile->update($data);
			return response()->json([
				'success' => true,
				'message' => 'Profile kampus berhasil diperbarui',
				'data' => $profile
			], 200);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Gagal memperbarui profile kampus',
				'error' => $e->getMessage()
			], 500);
		}
	}

	public function destroy($id = null)
	{
		try {
			// Karena hanya ada satu data, ambil data pertama (ignore ID)
			$profile = ProfileKampus::first();
			
			if (!$profile) {
				return response()->json([
					'success' => false,
					'message' => 'Profile kampus tidak ditemukan'
				], 404);
			}
			
			// Hapus file gambar jika ada
			if ($profile->struktur_image && Storage::disk('public')->exists($profile->struktur_image)) {
				Storage::disk('public')->delete($profile->struktur_image);
			}
			
			$profile->delete();
			return response()->json([
				'success' => true,
				'message' => 'Profile kampus berhasil dihapus'
			], 200);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Gagal menghapus profile kampus',
				'error' => $e->getMessage()
			], 500);
		}
	}
}
