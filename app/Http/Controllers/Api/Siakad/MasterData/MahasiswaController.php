<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use Exception;
use App\Models\MasterData\Prodi;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\KelasPararel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\MasterData\Mahasiswa;
use Illuminate\Validation\ValidationException;

class MahasiswaController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            // Memuat relasi yang relevan
            $mahasiswas = Mahasiswa::with(['prodi', 'kelasPararel', 'dosenWali'])->get();
            $dataprodi = Prodi::all();
            $datadosen = Dosen::all();
            $datakelaspararel = KelasPararel::all();

            return response()->json([
                'success' => true,
                'message' => 'Daftar Mahasiswa',
                'data' => [
                    'mahasiswa'     => $mahasiswas,
                    'prodi'         => $dataprodi,
                    'dosen'         => $datadosen,
                    'kelas_pararel' => $datakelaspararel
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data mahasiswa.',
                'error' => $e->getMessage() // Hanya tampilkan pesan error jika debug=true
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $mahasiswa = Mahasiswa::with(['prodi', 'kelasPararel', 'dosenWali'])->find($id);

            if (!$mahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Mahasiswa',
                'data' => $mahasiswa
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_prodi' => 'required|exists:prodi,id',
                // 'id_kelas_pararel' => 'nullable|exists:kelas_pararel,id', // Bisa null saat pertama kali
                // 'id_dosen' => 'nullable|exists:dosen,id', // Bisa null saat pertama kali
                // 'user_id' => 'nullable|exists:user,id',
                'nim' => 'required|string|max:20|unique:mahasiswa,nim',
                'nama_mahasiswa' => 'required|string|max:255',
                'jenis_kelamin' => 'required|in:L,P',
                'tanggal_lahir' => 'required|date',
                'alamat' => 'nullable|string',
                'no_hp' => 'nullable|string|max:15',
                // 'email' => 'required|email|unique:mahasiswa,email',
                'asal_sekolah' => 'nullable|string|max:255',
                'nama_orang_tua' => 'nullable|string|max:255',
                'no_hp_orang_tua' => 'nullable|string|max:15',
                'status' => 'required|in:Aktif,Cuti,DO,Lulus', // Default 'Aktif' biasanya di model
                'angkatan' => 'required|integer|min:1900|max:' . (date('Y') + 10) // Sesuaikan rentang tahun
                // Tambahkan validasi untuk field lain jika ada
            ]);


            $mahasiswa = Mahasiswa::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Mahasiswa berhasil dibuat.',
                'data' => $mahasiswa
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
                'message' => 'Terjadi kesalahan saat membuat mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $mahasiswa = Mahasiswa::find($id);

            if (!$mahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_prodi' => 'sometimes|exists:prodi,id',
                // 'id_kelas_pararel' => 'nullable|sometimes|exists:kelas_pararel,id',
                // 'id_dosen' => 'nullable|sometimes|exists:dosen,id',
                // 'user_id' => 'nullable|sometimes|exists:user,id',
                'nim' => 'sometimes|string|max:20|unique:mahasiswa,nim,' . $id,
                'nama_mahasiswa' => 'sometimes|string|max:255',
                'jenis_kelamin' => 'sometimes|in:L,P',
                'tanggal_lahir' => 'sometimes|date',
                'alamat' => 'nullable|string',
                'no_hp' => 'nullable|string|max:15',
                // 'email' => 'sometimes|email|unique:mahasiswa,email,' . $id,
                'asal_sekolah' => 'nullable|string|max:255',
                'nama_orang_tua' => 'nullable|string|max:255',
                'no_hp_orang_tua' => 'nullable|string|max:15',
                'status' => 'sometimes|in:Aktif,Cuti,DO,Lulus',
                'angkatan' => 'sometimes|integer|min:1900|max:' . (date('Y') + 10)
                // Tambahkan validasi untuk field lain jika ada
            ]);

            $mahasiswa->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Mahasiswa berhasil diperbarui.',
                'data' => $mahasiswa
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
                'message' => 'Terjadi kesalahan saat memperbarui mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $mahasiswa = Mahasiswa::find($id);

            if (!$mahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa tidak ditemukan.'
                ], 404);
            }

            $mahasiswa->delete();

            return response()->json([
                'success' => true,
                'message' => 'Mahasiswa berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
