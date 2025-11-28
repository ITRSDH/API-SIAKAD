<?php

namespace App\Http\Controllers\Api\MasterData;

use Exception;
use Illuminate\Http\Request;
use App\Models\MasterData\Prodi;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\MasterData\Kurikulum;
use App\Models\MasterData\MataKuliah;
use Illuminate\Validation\ValidationException;

class MataKuliahController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            // Ambil data tanpa nested relasi untuk mata_kuliah
            $mataKuliahs = MataKuliah::all(); // Atau dengan ->with(['kurikulum']) jika kamu ingin relasi untuk ditampilkan, tapi jangan digunakan di filter JS
            // Ambil kurikulum dengan prodi
            $kurikulums = Kurikulum::with(['prodi'])->get();
            // Ambil prodi
            $prodis = Prodi::all();

            return response()->json([
                'success' => true,
                'message' => 'Data master berhasil diambil',
                'data' => [
                    'mata-kuliah' => $mataKuliahs, // Harus memiliki id_kurikulum
                    'kurikulum' => $kurikulums,    // Harus memiliki id_prodi
                    'prodi' => $prodis,
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data master.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAllData(): JsonResponse
    {
        try {
            // Memuat relasi kurikulum
            $mataKuliahs = MataKuliah::with(['kurikulum.prodi'])->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar Mata Kuliah',
                'data' => $mataKuliahs
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data mata kuliah.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $mataKuliah = MataKuliah::with(['kurikulum.prodi'])->find($id);

            if (!$mataKuliah) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mata Kuliah tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Mata Kuliah',
                'data' => $mataKuliah
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data mata kuliah.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_kurikulum' => 'required|exists:kurikulum,id',
                'kode_mk' => 'required|string|max:20|unique:mata_kuliah,kode_mk',
                'nama_mk' => 'required|string|max:255',
                'sks' => 'required|integer|min:0|max:10',
                'semester_rekomendasi' => 'required|integer|min:1|max:14', // Sesuaikan dengan jumlah semester maksimum
                'jenis' => 'required|in:Wajib,Pilihan',
                'deskripsi' => 'nullable|string',
                'teori' => 'required|integer|min:0',
                'seminar' => 'required|integer|min:0',
                'praktikum' => 'required|integer|min:0',
                'praktek_klinik' => 'required|integer|min:0', // Ditambahkan sesuai struktur2.txt
                // Tambahkan validasi untuk field lain jika ada
            ]);

            $mataKuliah = MataKuliah::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Mata Kuliah berhasil dibuat.',
                'data' => $mataKuliah
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
                'message' => 'Terjadi kesalahan saat membuat mata kuliah.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getEditData(string $id): JsonResponse
    {
        try {
            // Ambil data mata kuliah berdasarkan ID
            $mataKuliah = MataKuliah::with(['kurikulum.prodi'])->find($id);

            if (!$mataKuliah) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mata Kuliah tidak ditemukan.'
                ], 404);
            }

            // Ambil ID Kurikulum dari mata kuliah yang ditemukan
            $idKurikulumMk = $mataKuliah->id_kurikulum;

            // Ambil kurikulum yang sesuai (hanya satu, karena dari relasi)
            $kurikulumMk = Kurikulum::with('prodi')->find($idKurikulumMk);

            if (!$kurikulumMk) {
                // Ini adalah kasus error karena id_kurikulum di mata kuliah tidak valid
                return response()->json([
                    'success' => false,
                    'message' => 'Data kurikulum untuk mata kuliah ini tidak ditemukan.'
                ], 500);
            }

            // Ambil semua data prodi
            $prodi = Prodi::all();

            // Ambil semua kurikulum yang terkait dengan prodi dari mata kuliah yang sedang diedit
            $kurikulum = Kurikulum::where('id_prodi', $kurikulumMk->id_prodi)->get();

            return response()->json([
                'success' => true,
                'message' => 'Data master dan detail mata kuliah untuk edit',
                'data' => [
                    'mata-kuliah' => $mataKuliah,
                    'prodi' => $prodi,
                    'kurikulum' => $kurikulum,
                    'selected_prodi_id' => $kurikulumMk->id_prodi,
                    'selected_kurikulum_id' => $idKurikulumMk,
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data untuk edit.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $mataKuliah = MataKuliah::find($id);

            if (!$mataKuliah) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mata Kuliah tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_kurikulum' => 'sometimes|exists:kurikulum,id',
                'kode_mk' => 'sometimes|string|max:20|unique:mata_kuliah,kode_mk,' . $id,
                'nama_mk' => 'sometimes|string|max:255',
                'sks' => 'sometimes|integer|min:0|max:10',
                'semester_rekomendasi' => 'sometimes|integer|min:1|max:14',
                'jenis' => 'sometimes|in:Wajib,Pilihan',
                'deskripsi' => 'nullable|string',
                'teori' => 'sometimes|integer|min:0',
                'seminar' => 'sometimes|integer|min:0',
                'praktikum' => 'sometimes|integer|min:0',
                'praktek_klinik' => 'sometimes|integer|min:0',
                // Tambahkan validasi untuk field lain jika ada
            ]);

            $mataKuliah->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Mata Kuliah berhasil diperbarui.',
                'data' => $mataKuliah
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
                'message' => 'Terjadi kesalahan saat memperbarui mata kuliah.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $mataKuliah = MataKuliah::find($id);

            if (!$mataKuliah) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mata Kuliah tidak ditemukan.'
                ], 404);
            }

            $mataKuliah->delete();

            return response()->json([
                'success' => true,
                'message' => 'Mata Kuliah berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus mata kuliah.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
