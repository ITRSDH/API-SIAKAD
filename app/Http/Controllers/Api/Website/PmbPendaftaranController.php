<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Website\PmbPendaftaran;
use Illuminate\Validation\ValidationException;

class PmbPendaftaranController extends Controller
{
    public function index(Request $request)
	{
		try {
			// Ambil data pertama (hanya ada satu data)
			$profile = PmbPendaftaran::first();
			
			return response()->json([
				'success' => true,
				'message' => 'Data PMB Pendaftaran',
				'data' => $profile
			], 200);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Gagal mengambil data PMB Pendaftaran',
				'error' => $e->getMessage()
			], 500);
		}
	}

	public function store(Request $request)
	{
		try {
			$data = $request->validate([
				'deskripsi' => 'nullable|string',
                'tata_cara' => 'nullable|string',
			]);
			
			// Cek apakah sudah ada data profile kampus
			$existingProfile = PmbPendaftaran::first();
			
			if ($existingProfile) {
				// Jika sudah ada, lakukan update
				$existingProfile->update($data);
				
				return response()->json([
					'success' => true,
					'message' => 'PMB Pendaftaran berhasil diperbarui',
					'data' => $existingProfile
				], 200);
			} else {
				// Jika belum ada, buat data baru
				$profile = PmbPendaftaran::create($data);
				
				return response()->json([
					'success' => true,
					'message' => 'PMB Pendaftaran berhasil ditambahkan',
					'data' => $profile
				], 201);
			}
		} catch (ValidationException $e) {
			throw $e;
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Gagal menyimpan PMB Pendaftaran',
				'error' => $e->getMessage()
			], 500);
		}
	}

	public function show($id = null)
	{
		try {
			// Karena hanya ada satu data, ambil data pertama (ignore ID)
			$profile = PmbPendaftaran::first();
			
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
				'message' => 'Gagal mengambil detail PMB Pendaftaran',
				'error' => $e->getMessage()
			], 500);
		}
	}
}
