<?php

namespace App\Http\Controllers\Api\MasterData;

use Exception;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\KelasMk;
use App\Models\MasterData\Semester;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\MasterData\BebanAjarDosen;
use Illuminate\Validation\ValidationException;

class BebanAjarDosenController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $bebanAjarDosens = BebanAjarDosen::with(['dosen', 'kelasMk', 'semester'])->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar Beban Ajar Dosen',
                'data' => $bebanAjarDosens
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data beban ajar dosen.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_dosen' => 'required|exists:dosen,id',
                'id_kelas_mk' => 'required|exists:kelas_mk,id',
                'id_semester' => 'required|exists:semester,id',
                'jumlah_jam' => 'required|integer|min:1',
            ]);

            $bebanAjarDosen = BebanAjarDosen::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Beban Ajar Dosen berhasil ditambahkan.',
                'data' => $bebanAjarDosen
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menambahkan beban ajar dosen.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $bebanAjarDosen = BebanAjarDosen::with(['dosen', 'kelasMk', 'semester'])->find($id);

            if (!$bebanAjarDosen) {
                return response()->json([
                    'success' => false,
                    'message' => 'Beban Ajar Dosen tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Beban Ajar Dosen',
                'data' => $bebanAjarDosen
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data beban ajar dosen.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $bebanAjarDosen = BebanAjarDosen::find($id);

            if (!$bebanAjarDosen) {
                return response()->json([
                    'success' => false,
                    'message' => 'Beban Ajar Dosen tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_dosen' => 'sometimes|exists:dosen,id',
                'id_kelas_mk' => 'sometimes|exists:kelas_mk,id',
                'id_semester' => 'sometimes|exists:semester,id',
                'jumlah_jam' => 'sometimes|integer|min:1',
            ]);

            $bebanAjarDosen->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Beban Ajar Dosen berhasil diperbarui.',
                'data' => $bebanAjarDosen
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui beban ajar dosen.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $bebanAjarDosen = BebanAjarDosen::find($id);

            if (!$bebanAjarDosen) {
                return response()->json([
                    'success' => false,
                    'message' => 'Beban Ajar Dosen tidak ditemukan.'
                ], 404);
            }

            $bebanAjarDosen->delete();

            return response()->json([
                'success' => true,
                'message' => 'Beban Ajar Dosen berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus beban ajar dosen.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
