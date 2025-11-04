<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Website\StoreGaleriRequest;
use App\Http\Requests\Website\UpdateGaleriRequest;
use Illuminate\Support\Facades\Storage;
use App\Models\Website\Galeri;

class GaleriController extends Controller
{
	public function index(Request $request)
	{
		try {
			$galeri = Galeri::select('id', 'judul', 'kategori', 'gambar', 'deskripsi', 'tanggal', 'created_at')->orderBy('created_at', 'desc')->get();
			return response()->json([
				'success' => true,
				'message' => 'Daftar galeri',
				'data' => $galeri
			], 200);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Gagal mengambil data galeri',
				'error' => $e->getMessage()
			], 500);
		}
	}

	public function store(StoreGaleriRequest $request)
	{
		try {
			$data = $request->validated();

			if ($request->hasFile('gambar')) {
				$file = $request->file('gambar');
				$path = $file->store('galeri', 'public');
				$data['gambar'] = $path;
			}

			$galeri = Galeri::create($data);
			return response()->json([
				'success' => true,
				'message' => 'Galeri berhasil ditambahkan',
				'data' => $galeri
			], 201);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Gagal menambahkan galeri',
				'error' => $e->getMessage()
			], 500);
		}
	}

	public function show($id)
	{
		try {
			$galeri = Galeri::findOrFail($id);
			return response()->json([
				'success' => true,
				'message' => 'Detail galeri',
				'data' => $galeri
			], 200);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Galeri tidak ditemukan',
				'error' => $e->getMessage()
			], 404);
		}
	}

	public function update(UpdateGaleriRequest $request, $id)
	{
		try {
			$galeri = Galeri::findOrFail($id);
			$data = $request->validated();

			if ($request->hasFile('gambar')) {
				// Hapus gambar lama jika ada
				if ($galeri->gambar && Storage::disk('public')->exists($galeri->gambar)) {
					Storage::disk('public')->delete($galeri->gambar);
				}
				$file = $request->file('gambar');
				$path = $file->store('galeri', 'public');
				$data['gambar'] = $path;
			}

			$galeri->update($data);
			return response()->json([
				'success' => true,
				'message' => 'Galeri berhasil diperbarui',
				'data' => $galeri
			], 200);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Gagal memperbarui galeri',
				'error' => $e->getMessage()
			], 500);
		}
	}

	public function destroy($id)
	{
		try {
			$galeri = Galeri::findOrFail($id);
			$galeri->delete();
			return response()->json([
				'success' => true,
				'message' => 'Galeri berhasil dihapus'
			], 200);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Gagal menghapus galeri',
				'error' => $e->getMessage()
			], 500);
		}
	}
}
