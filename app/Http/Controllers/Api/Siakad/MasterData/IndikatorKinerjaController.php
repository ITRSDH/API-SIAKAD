<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;
use App\Models\MasterData\Cpl;
use App\Models\MasterData\IndikatorKinerja;

class IndikatorKinerjaController extends Controller
{
    /**
     * Get all Indikator Kinerja for a specific CPL
     */
    public function index(string $id_cpl): JsonResponse
    {
        try {
            $indikatorKinerja = IndikatorKinerja::where('id_cpl', $id_cpl)->get();

            return response()->json([
                'success' => true,
                'message' => 'Data Indikator Kinerja berhasil diambil',
                'data' => $indikatorKinerja
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data Indikator Kinerja.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new Indikator Kinerja
     */
    public function store(Request $request, string $id_cpl): JsonResponse
    {
        try {
            $request->validate([
                'kode_ik_cpl' => 'required|string|max:50|unique:indikator_kinerja_cpl,kode_ik_cpl',
                'deskripsi_ik_cpl_indonesia' => 'required|string',
                'deskripsi_ik_cpl_english' => 'nullable|string',
                'kategori_ik_cpl' => 'nullable|in:KK,KU,P,S'
            ]);

            $data = $request->all();
            $data['id_cpl'] = $id_cpl;

            $indikatorKinerja = IndikatorKinerja::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Indikator Kinerja berhasil dibuat',
                'data' => $indikatorKinerja
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat Indikator Kinerja.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show specific Indikator Kinerja
     */
    public function show(string $id): JsonResponse
    {
        try {
            $indikatorKinerja = IndikatorKinerja::with('cpl')->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Data Indikator Kinerja berhasil diambil',
                'data' => $indikatorKinerja
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Indikator Kinerja tidak ditemukan.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update Indikator Kinerja
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'kode_ik_cpl' => 'required|string|max:50|unique:indikator_kinerja_cpl,kode_ik_cpl,' . $id,
                'deskripsi_ik_cpl_indonesia' => 'required|string',
                'deskripsi_ik_cpl_english' => 'nullable|string',
                'kategori_ik_cpl' => 'nullable|in:KK,KU,P,S'
            ]);

            $indikatorKinerja = IndikatorKinerja::findOrFail($id);
            $indikatorKinerja->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Indikator Kinerja berhasil diperbarui',
                'data' => $indikatorKinerja
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui Indikator Kinerja.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete Indikator Kinerja
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $indikatorKinerja = IndikatorKinerja::findOrFail($id);
            $indikatorKinerja->delete();

            return response()->json([
                'success' => true,
                'message' => 'Indikator Kinerja berhasil dihapus'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus Indikator Kinerja.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get CPL with its Indikator Kinerja
     */
    public function getCplWithIndikator(string $id_cpl): JsonResponse
    {
        try {
            $cpl = Cpl::with('indikatorKinerja')->findOrFail($id_cpl);

            return response()->json([
                'success' => true,
                'message' => 'Data CPL dan Indikator Kinerja berhasil diambil',
                'data' => $cpl
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'CPL tidak ditemukan.',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}
