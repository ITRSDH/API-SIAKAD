<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use Exception;
use Illuminate\Http\Request;
use App\Models\MasterData\ProfileLulusan;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class ProfileLulusanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $profileLulusan = ProfileLulusan::with(['cpl'])->get();

            return response()->json([
                'success' => true,
                'message' => 'Data Profile Lulusan berhasil diambil',
                'data' => $profileLulusan
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data Profile Lulusan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_prodi' => 'required|string',
                'kode_pl' => 'required|string|max:50|unique:profile_lulusan,kode_pl',
                'profile_lulusan' => 'required|string|max:255',
                'deskripsi_profile_lulusan_indonesia' => 'required|string',
                'deskripsi_profile_lulusan_english' => 'nullable|string',
                'profesi_lulusan' => 'nullable|string'
            ]);

            $profileLulusan = ProfileLulusan::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Profile Lulusan berhasil dibuat',
                'data' => $profileLulusan
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat Profile Lulusan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $profileLulusan = ProfileLulusan::with(['cpl'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Data Profile Lulusan berhasil diambil',
                'data' => $profileLulusan
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Profile Lulusan tidak ditemukan.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $profileLulusan = ProfileLulusan::findOrFail($id);

            $request->validate([
                'id_prodi' => 'required|string',
                'kode_pl' => 'required|string|max:50|unique:profile_lulusan,kode_pl,' . $id,
                'profile_lulusan' => 'required|string|max:255',
                'deskripsi_profile_lulusan_indonesia' => 'required|string',
                'deskripsi_profile_lulusan_english' => 'nullable|string',
                'profesi_lulusan' => 'nullable|string'
            ]);

            $profileLulusan->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Profile Lulusan berhasil diperbarui',
                'data' => $profileLulusan
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui Profile Lulusan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $profileLulusan = ProfileLulusan::findOrFail($id);
            $profileLulusan->delete();

            return response()->json([
                'success' => true,
                'message' => 'Profile Lulusan berhasil dihapus'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus Profile Lulusan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
