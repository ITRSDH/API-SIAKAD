<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use Exception;
use Illuminate\Http\Request;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\Prodi;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;

class DosenController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            // Memuat relasi prodi
            $dosens = Dosen::with(['prodi'])->get();
            $dataprodi = Prodi::all();
            return response()->json([
                'success' => true,
                'message' => 'Daftar Dosen',
                'data' => [
                    'dosen' => $dosens,
                    'prodi' => $dataprodi
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data dosen.',
                'error' => $e->getMessage() // Hanya tampilkan pesan error jika debug=true
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $dosen = Dosen::with(['prodi'])->find($id);

            if (!$dosen) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dosen tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Dosen',
                'data' => $dosen
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data dosen.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_prodi' => 'required|exists:prodi,id',
                // 'user_id' => 'required|exists:user,id',
                'nidn' => 'nullable|string|unique:dosen,nidn',
                'nup' => 'nullable|string|unique:dosen,nup',
                'nama_dosen' => 'required|string|max:255',
                'jenis_kelamin' => 'required|in:L,P',
                'tanggal_lahir' => 'nullable|date',
                'alamat' => 'nullable|string',
                'no_hp' => 'nullable|string|max:15',
                // 'email' => 'nullable|email|unique:dosen,email',
                // 'jabatan_akademik' => 'nullable|string|max:255', // Asisten Ahli, Lektor, dll
                // 'pangkat_golongan' => 'nullable|string|max:255',
                // 'status_aktif' => 'boolean', // Default: true
                // Tambahkan validasi untuk field lain jika ada
            ]);

            $dosen = Dosen::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Dosen berhasil dibuat.',
                'data' => $dosen
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
                'message' => 'Terjadi kesalahan saat membuat dosen.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $dosen = Dosen::find($id);

            if (!$dosen) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dosen tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_prodi' => 'sometimes|exists:prodi,id',
                // 'user_id' => 'sometimes|exists:user,id',
                'nidn' => 'nullable|string|unique:dosen,nidn,' . $id,
                'nup' => 'nullable|string|unique:dosen,nup,' . $id,
                'nama_dosen' => 'sometimes|string|max:255',
                'jenis_kelamin' => 'sometimes|in:L,P',
                'tanggal_lahir' => 'nullable|date',
                'alamat' => 'nullable|string',
                'no_hp' => 'nullable|string|max:15',
                // 'email' => 'nullable|email|unique:dosen,email,' . $id,
                // 'jabatan_akademik' => 'nullable|string|max:255',
                // 'pangkat_golongan' => 'nullable|string|max:255',
                // 'status_aktif' => 'boolean',
                // Tambahkan validasi untuk field lain jika ada
            ]);

            $dosen->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Dosen berhasil diperbarui.',
                'data' => $dosen
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
                'message' => 'Terjadi kesalahan saat memperbarui dosen.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $dosen = Dosen::find($id);

            if (!$dosen) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dosen tidak ditemukan.'
                ], 404);
            }

            $dosen->delete();

            return response()->json([
                'success' => true,
                'message' => 'Dosen berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus dosen.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
