<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\Prodi;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\MasterData\JenjangPendidikan;

class ProdiController extends Controller
{
    /**
     * Get all program studies with related data including dosen list.
     */
    public function index(): JsonResponse
    {
        try {
            $prodi = Prodi::with(['jenjang', 'kaprodi:id,nama_dosen'])->get();
            $jenjang_pendidikan = JenjangPendidikan::get();

            // Exclude dosen who are already kaprodi
            $dosen_list = Dosen::whereNotIn('id', function ($query) {
                $query->select('id_kaprodi')
                    ->from('prodi')
                    ->whereNotNull('id_kaprodi');
            })
                ->select('id', 'nama_dosen', 'nup')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data All Program Studi berhasil diambil',
                'data' => [
                    'prodi' => $prodi,
                    'jenjang_pendidikan' => $jenjang_pendidikan,
                    'dosen_list' => $dosen_list,
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data All Program Studi.',
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
                'kode_prodi' => 'required|unique:prodi,kode_prodi',
                'nama_prodi' => 'required|string|max:100',
                'id_jenjang_pendidikan' => 'required|exists:jenjang_pendidikan,id',
                'id_kaprodi' => 'nullable|exists:dosen,id',
                'akreditasi' => 'nullable|in:A,B,C,Unggul',
                'tahun_berdiri' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
                'kuota' => 'nullable|integer|min:0',
                'gelar_lulusan' => 'nullable|string|max:100',
            ]);

            $prodi = Prodi::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Program studi berhasil ditambahkan',
                'data' => $prodi->load('jenjang', 'kaprodi:id,nama_dosen')
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan program studi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        try {
            if (!Str::isUuid($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID harus berupa UUID yang valid'
                ], 400);
            }

            $prodi = Prodi::with('jenjang', 'kaprodi:id,nama_dosen')->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Detail program studi',
                'data' => $prodi
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Program studi tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            if (!Str::isUuid($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID harus berupa UUID yang valid'
                ], 400);
            }

            $prodi = Prodi::findOrFail($id);

            $request->validate([
                'kode_prodi' => 'sometimes|required|unique:prodi,kode_prodi,' . $id,
                'nama_prodi' => 'sometimes|required|string|max:100',
                'id_jenjang_pendidikan' => 'sometimes|required|exists:jenjang_pendidikan,id',
                'id_kaprodi' => 'sometimes|nullable|exists:dosen,id',
                'akreditasi' => 'nullable|in:A,B,C,Unggul',
                'tahun_berdiri' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
                'kuota' => 'nullable|integer|min:0',
                'gelar_lulusan' => 'nullable|string|max:100',
            ]);

            $prodi->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Program studi berhasil diperbarui',
                'data' => $prodi->load('jenjang', 'kaprodi:id,nama_dosen')
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui program studi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        try {
            if (!Str::isUuid($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID harus berupa UUID yang valid'
                ], 400);
            }

            $prodi = Prodi::findOrFail($id);

            $prodi->delete();

            return response()->json([
                'success' => true,
                'message' => 'Program studi berhasil dihapus'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus program studi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the assigned kaprodi for a program study.
     */
    public function updateKaprodi(Request $request, $id): JsonResponse
    {
        try {
            if (!Str::isUuid($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID harus berupa UUID yang valid'
                ], 400);
            }

            $request->validate([
                'id_kaprodi' => 'nullable|exists:dosen,id|unique:prodi,id_kaprodi' // Tambahkan unique constraint
            ]);

            $prodi = Prodi::findOrFail($id);
            $prodi->id_kaprodi = $request->id_kaprodi;
            $prodi->save();

            return response()->json([
                'success' => true,
                'message' => 'Kaprodi berhasil diperbarui',
                'data' => $prodi->load('kaprodi:id,nama_dosen')
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui kaprodi',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
