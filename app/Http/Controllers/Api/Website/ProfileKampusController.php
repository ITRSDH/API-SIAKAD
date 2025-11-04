<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Website\ProfileKampus;
use App\Http\Requests\Website\StoreProfileKampusRequest;
use App\Http\Requests\Website\UpdateProfileKampusRequest;
use Illuminate\Support\Facades\Storage;

class ProfileKampusController extends Controller
{
	public function index(Request $request)
	{
		try {
			$profiles = ProfileKampus::select([
				'id', 'judul', 'deskripsi', 'visi', 'misi', 'struktur_image', 'fasilitas', 'created_at', 'updated_at'
			])->orderBy('created_at', 'desc')->get();
			return response()->json([
				'success' => true,
				'message' => 'Daftar profile kampus',
				'data' => $profiles
			], 200);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Gagal mengambil data profile kampus',
				'error' => $e->getMessage()
			], 500);
		}
	}

	public function store(StoreProfileKampusRequest $request)
	{
		try {
			$data = $request->validated();
			if ($request->hasFile('struktur_image')) {
				$file = $request->file('struktur_image');
				$path = $file->store('profile_kampus', 'public');
				$data['struktur_image'] = $path;
			}
			$profile = ProfileKampus::create($data);
			return response()->json([
				'success' => true,
				'message' => 'Profile kampus berhasil ditambahkan',
				'data' => $profile
			], 201);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Gagal menambahkan profile kampus',
				'error' => $e->getMessage()
			], 500);
		}
	}

	public function show($id)
	{
		try {
			$profile = ProfileKampus::findOrFail($id);
			return response()->json([
				'success' => true,
				'message' => 'Detail profile kampus',
				'data' => $profile
			], 200);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Profile kampus tidak ditemukan',
				'error' => $e->getMessage()
			], 404);
		}
	}

	public function update(UpdateProfileKampusRequest $request, $id)
	{
		try {
			$profile = ProfileKampus::findOrFail($id);
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

	public function destroy($id)
	{
		try {
			$profile = ProfileKampus::findOrFail($id);
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
