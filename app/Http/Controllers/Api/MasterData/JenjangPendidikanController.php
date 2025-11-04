<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\JenjangPendidikan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class JenjangPendidikanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // $jenjang = JenjangPendidikan::with('prodi')->paginate($request->paginator, ['*'], 'page', $request->page);
            $jenjang = JenjangPendidikan::with('prodi')->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar jenjang pendidikan',
                'data' => $jenjang
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data jenjang pendidikan',
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
                'kode_jenjang' => 'required|unique:jenjang_pendidikan,kode_jenjang',
                'nama_jenjang' => 'required|string|max:50',
                'deskripsi' => 'nullable|string',
                'jumlah_semester' => 'nullable|integer|min:1'
            ]);

            $jenjang = JenjangPendidikan::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Jenjang pendidikan berhasil ditambahkan',
                'data' => $jenjang
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan jenjang pendidikan',
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

            $jenjang = JenjangPendidikan::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Detail jenjang pendidikan',
                'data' => $jenjang
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Jenjang pendidikan tidak ditemukan',
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

            $jenjang = JenjangPendidikan::findOrFail($id);

            $request->validate([
                'kode_jenjang' => 'sometimes|required|unique:jenjang_pendidikan,kode_jenjang,' . $id,
                'nama_jenjang' => 'sometimes|required|string|max:50',
                'deskripsi' => 'nullable|string',
                'jumlah_semester' => 'sometimes|integer|min:1'
            ]);

            $jenjang->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Jenjang pendidikan berhasil diperbarui',
                'data' => $jenjang
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui jenjang pendidikan',
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

            $jenjang = JenjangPendidikan::findOrFail($id);

            $jenjang->delete();

            return response()->json([
                'success' => true,
                'message' => 'Jenjang pendidikan berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus jenjang pendidikan',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
