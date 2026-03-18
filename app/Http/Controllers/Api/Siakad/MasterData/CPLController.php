<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;
use App\Models\MasterData\Cpl;

class CPLController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(string $id_prodi): JsonResponse
    {
        try {

            $Cpl = Cpl::with([
                'indikatorKinerja' => function ($q) {
                    $q->orderBy('kode_ik_cpl');
                }
            ])
                ->where('id_prodi', $id_prodi)
                ->orderBy('kode_cpl')
                ->get()
                ->map(function ($cpl) {

                    return [
                        'id' => $cpl->id,
                        'kode_cpl' => $cpl->kode_cpl,
                        'deskripsi_cpl_indonesia' => $cpl->deskripsi_cpl_indonesia,
                        'deskripsi_cpl_english' => $cpl->deskripsi_cpl_english,
                        'kategori_cpl' => $cpl->kategori_cpl,

                        'indikator_kinerja' => $cpl->indikatorKinerja->map(function ($ik) {

                            return [
                                'id' => $ik->id,
                                'kode_ik_cpl' => $ik->kode_ik_cpl,
                                'deskripsi_ik_cpl_indonesia' => $ik->deskripsi_ik_cpl_indonesia,
                                'deskripsi_ik_cpl_english' => $ik->deskripsi_ik_cpl_english,
                                'kategori_ik_cpl' => $ik->kategori_ik_cpl,
                                'id_cpl' => $ik->id_cpl
                            ];
                        })

                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Data Capaian Pembelajaran Lulusan berhasil diambil',
                'data' => $Cpl
            ], 200);
        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data Capaian Pembelajaran Lulusan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, string $id_prodi): JsonResponse
    {
        try {
            $request->validate([
                'kode_cpl' => 'required|string|max:50|unique:cpl,kode_cpl',
                // 'cpl' => 'required|string|max:255',
                'deskripsi_cpl_indonesia' => 'required|string',
                'deskripsi_cpl_english' => 'nullable|string',
                'kategori_cpl' => 'nullable|in:KK,KU,P,S'
            ]);

            $data = $request->all();
            $data['id_prodi'] = $id_prodi;

            $Cpl = Cpl::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Capaian Pembelajaran Lulusan berhasil dibuat',
                'data' => $Cpl
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat Capaian Pembelajaran Lulusan.',
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
            $Cpl = Cpl::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Data Capaian Pembelajaran Lulusan berhasil diambil',
                'data' => $Cpl
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Capaian Pembelajaran Lulusan tidak ditemukan.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id, string $id_prodi): JsonResponse
    {
        try {
            $request->validate([
                'kode_cpl' => 'required|string|max:50|unique:cpl,kode_cpl,' . $id,
                // 'cpl' => 'required|string|max:255',
                'deskripsi_cpl_indonesia' => 'required|string',
                'deskripsi_cpl_english' => 'nullable|string',
                'kategori_cpl' => 'nullable|in:KK,KU,P,S'
            ]);

            $Cpl = Cpl::where('id_prodi', $id_prodi)
                ->findOrFail($id);

            $Cpl->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Capaian Pembelajaran Lulusan berhasil diperbarui',
                'data' => $Cpl
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui Capaian Pembelajaran Lulusan.',
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
            $Cpl = Cpl::findOrFail($id);

            $Cpl->delete();

            return response()->json([
                'success' => true,
                'message' => 'Capaian Pembelajaran Lulusan berhasil dihapus'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus Capaian Pembelajaran Lulusan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
