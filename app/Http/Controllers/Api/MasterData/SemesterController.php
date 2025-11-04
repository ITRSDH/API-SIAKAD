<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\Semester;
use App\Models\MasterData\TahunAkademik;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SemesterController extends Controller
{
    public function getAllData(): JsonResponse
    {
        try {
            $semester = Semester::with(['tahunAkademik'])->get();
            // Ambil jenjang pendidikan dengan prodi
            $tahunAkademik = TahunAkademik::get();

            return response()->json([
                'success' => true,
                'message' => 'Data All Semester berhasil diambil',
                'data' => [
                    'semester' => $semester,
                    'tahunAkademik' => $tahunAkademik,
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data All Semester.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index(): JsonResponse
    {
        try {
            $semesters = Semester::with(['tahunAkademik'])->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar Semester',
                'data' => $semesters
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data semester.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_tahun_akademik' => 'required|exists:tahun_akademik,id',
                'nama_semester' => 'required|in:Ganjil,Genap',
                'kode_semester' => 'required|string|max:255',
                'tanggal_mulai' => 'required|date',
                'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
                'status' => 'required|in:Aktif,Selesai,Akan Datang',
            ]);

            $semester = Semester::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Semester berhasil ditambahkan.',
                'data' => $semester
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
                'message' => 'Terjadi kesalahan saat menambahkan semester.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $semester = Semester::with(['tahunAkademik'])->find($id);

            if (!$semester) {
                return response()->json([
                    'success' => false,
                    'message' => 'Semester tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Semester',
                'data' => $semester
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data semester.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $semester = Semester::find($id);

            if (!$semester) {
                return response()->json([
                    'success' => false,
                    'message' => 'Semester tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_tahun_akademik' => 'sometimes|exists:tahun_akademik,id',
                'nama_semester' => 'sometimes|in:Ganjil,Genap',
                'kode_semester' => 'sometimes|string|max:255',
                'tanggal_mulai' => 'sometimes|date',
                'tanggal_selesai' => 'sometimes|date|after_or_equal:tanggal_mulai',
                'status' => 'sometimes|in:Aktif,Selesai,Akan Datang',
            ]);

            $semester->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Semester berhasil diperbarui.',
                'data' => $semester
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
                'message' => 'Terjadi kesalahan saat memperbarui semester.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $semester = Semester::find($id);

            if (!$semester) {
                return response()->json([
                    'success' => false,
                    'message' => 'Semester tidak ditemukan.'
                ], 404);
            }

            $semester->delete();

            return response()->json([
                'success' => true,
                'message' => 'Semester berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus semester.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
