<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\MasterData\Semester;
use App\Models\MasterData\TahunAkademik;

class TahunAkademikController extends Controller
{
    /**
     * ============================
     * GET: LIST TAHUN AKADEMIK
     * ============================
     */
    public function index(): JsonResponse
    {
        try {
            $data = TahunAkademik::with('semester')
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data tahun akademik',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ============================
     * GET: DETAIL TAHUN AKADEMIK
     * ============================
     */
    public function show(string $id): JsonResponse
    {
        try {
            $data = TahunAkademik::with('semester')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tahun akademik tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * ============================
     * POST: CREATE TAHUN AKADEMIK
     * ============================
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tahun_akademik' => 'required|string|max:20',
            'semester' => 'required|array|min:1',
            'semester.*.nama_semester' => 'required|in:Ganjil,Genap',
            'semester.*.tanggal_mulai' => 'required|date',
            'semester.*.tanggal_selesai' => 'required|date|after:semester.*.tanggal_mulai'
        ]);

        DB::beginTransaction();

        try {
            $tahun = TahunAkademik::create([
                'id' => Str::uuid(),
                'tahun_akademik' => $request->tahun_akademik,
                'status_aktif' => false
            ]);

            foreach ($request->semester as $item) {
                Semester::create([
                    'id' => Str::uuid(),
                    'id_tahun_akademik' => $tahun->id,
                    'nama_semester' => $item['nama_semester'],
                    'kode_semester' => strtoupper($item['nama_semester']),
                    'tanggal_mulai' => $item['tanggal_mulai'],
                    'tanggal_selesai' => $item['tanggal_selesai'],
                    'status' => 'Akan Datang'
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tahun akademik berhasil ditambahkan'
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan tahun akademik',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ============================
     * PUT: UPDATE TAHUN AKADEMIK
     * ============================
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'tahun_akademik' => 'required|string|max:20',
            'semester' => 'required|array|min:1',
            'semester.*.id' => 'required|exists:semester,id',
            'semester.*.tanggal_mulai' => 'required|date',
            'semester.*.tanggal_selesai' => 'required|date'
        ]);

        DB::beginTransaction();

        try {
            $tahun = TahunAkademik::findOrFail($id);

            $tahun->update([
                'tahun_akademik' => $request->tahun_akademik
            ]);

            foreach ($request->semester as $item) {
                Semester::where('id', $item['id'])->update([
                    'tanggal_mulai' => $item['tanggal_mulai'],
                    'tanggal_selesai' => $item['tanggal_selesai']
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tahun akademik berhasil diperbarui'
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui tahun akademik',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ============================
     * DELETE: HAPUS TAHUN AKADEMIK
     * ============================
     */
    public function destroy(string $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            Semester::where('id_tahun_akademik', $id)->delete();
            TahunAkademik::where('id', $id)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tahun akademik berhasil dihapus'
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus tahun akademik',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ============================
     * SET TAHUN AKADEMIK AKTIF
     * ============================
     */
    public function setTahunAktif(string $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            TahunAkademik::where('status_aktif', true)
                ->update(['status_aktif' => false]);

            TahunAkademik::where('id', $id)
                ->update(['status_aktif' => true]);

            // Reset semester aktif
            Semester::where('status', 'Aktif')
                ->update(['status' => 'Selesai']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tahun akademik berhasil diaktifkan. Silakan pilih semester aktif.'
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengaktifkan tahun akademik',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ============================
     * SET SEMESTER AKTIF
     * ============================
     */
    public function setSemesterAktif(string $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $semester = Semester::with('tahunAkademik')->findOrFail($id);

            // ❌ VALIDASI WAJIB
            if (!$semester->tahunAkademik->status_aktif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat mengaktifkan semester karena Tahun Akademik belum aktif'
                ], 422);
            }

            // Nonaktifkan semester aktif sebelumnya
            Semester::where('status', 'Aktif')
                ->update(['status' => 'Selesai']);

            // Aktifkan semester terpilih
            $semester->update([
                'status' => 'Aktif'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Semester berhasil diaktifkan'
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengaktifkan semester',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
