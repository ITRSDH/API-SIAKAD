<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\TahunAkademik;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class TahunAkademikController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // $tahunAkademik = TahunAkademik::paginate($request->paginator, ['*'], 'page', $request->page);
            $tahunAkademik = TahunAkademik::get();

            return response()->json([
                'success' => true,
                'message' => 'Data tahun akademik berhasil diambil',
                'data' => $tahunAkademik
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
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
                'tahun_akademik' => 'required|string|regex:/^\d{4}\/\d{4}$/',
                'status_aktif' => 'boolean'
            ]);

            $tahunAkademik = TahunAkademik::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Tahun akademik berhasil ditambahkan',
                'data' => $tahunAkademik
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        try {
            $tahunAkademik = TahunAkademik::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Data tahun akademik ditemukan',
                'data' => $tahunAkademik
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'tahun_akademik' => 'sometimes|string|regex:/^\d{4}\/\d{4}$/',
                'status_aktif' => 'sometimes|boolean'
            ]);

            $tahunAkademik = TahunAkademik::findOrFail($id);

            $tahunAkademik->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Tahun akademik berhasil diperbarui',
                'data' => $tahunAkademik
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $tahunAkademik = TahunAkademik::findOrFail($id);
            $tahunAkademik->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tahun akademik berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set tahun akademik aktif
     */
    public function setAktif($id): JsonResponse
    {
        try {
            $tahunAkademik = TahunAkademik::findOrFail($id);

            // Reset semua tahun akademik lain menjadi tidak aktif
            TahunAkademik::where('status_aktif', true)->update(['status_aktif' => false]);

            $tahunAkademik->update(['status_aktif' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Tahun akademik aktif berhasil diatur',
                'data' => $tahunAkademik
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengatur tahun akademik aktif: ' . $e->getMessage()
            ], 500);
        }
    }
}
