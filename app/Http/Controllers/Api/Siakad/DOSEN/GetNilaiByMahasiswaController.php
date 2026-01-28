<?php

namespace App\Http\Controllers\Api\Siakad\DOSEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\MasterData\KelasMk;
use App\Http\Controllers\Controller;
use App\Models\MasterData\KrsDetail;
use App\Models\MasterData\Mahasiswa;
use Illuminate\Support\Facades\Auth;
use App\Models\MasterData\DosenKelasMk;

class GetNilaiByMahasiswaController extends Controller
{
    public function getNilaiMahasiswa(string $id_kelas_mk, Request $request): JsonResponse
    {
        try {
            // 1. Ambil ID Dosen dari User yang Login
            $id_dosen_login = Auth::user()?->dosen?->id;

            if (!$id_dosen_login) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses Ditolak. Anda bukan dosen atau tidak login.',
                ], 403);
            }

            // 2. Verifikasi Akses: Apakah dosen ini mengajar kelas MK ini?
            $akses_kelas = DosenKelasMk::where('id_kelas_mk', $id_kelas_mk)
                ->where('id_dosen', $id_dosen_login)
                ->exists(); // exists() lebih efisien jika hanya cek keberadaan

            if (!$akses_kelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses Ditolak. Anda tidak mengajar kelas ini.',
                ], 403);
            }

            // 3. Ambil Data Kelas MK (opsional, bisa digunakan untuk info tambahan)
            $kelas_mk = KelasMk::with(['mataKuliah:id,nama_mk,kode_mk,sks', 'kelasPararel:id,nama_kelas', 'semester:id,nama_semester,kode_semester'])
                ->find($id_kelas_mk); // find() langsung return model atau null

            if (!$kelas_mk) {
                // Harusnya gagal di DosenKelasMk check dulu, tapi tetap dijaga
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas MK tidak ditemukan.',
                ], 404);
            }

            // 4. Ambil Daftar Mahasiswa
            // Langkah: KelasMK -> KRS_Detail (id_kelas_mk) -> KRS (id_krs) -> Mahasiswa (id_mahasiswa)
            $krs_detail_ids = KrsDetail::where('id_kelas_mk', $id_kelas_mk)
                ->pluck('id_krs'); // Ambil array of id_krs

            $mahasiswa_list = Mahasiswa::whereIn('id', function ($query) use ($krs_detail_ids) {
                $query->select('id_mahasiswa')
                    ->from('krs') // Tabel KRS
                    ->whereIn('id', $krs_detail_ids); // Filter KRS by id_krs
            })
                ->with(['prodi:id,nama_prodi,kode_prodi', 'kelasPararel:id,nama_kelas'])
                ->select('id', 'nim', 'nama_mahasiswa', 'id_kelas_pararel', 'id_prodi') // Select only necessary fields
                ->orderBy('nama_mahasiswa')
                ->get();

            // 5. Kembalikan Response Sukses
            return response()->json([
                'success' => true,
                'data' => [
                    'kelas_mk' => $kelas_mk, // Info kelas
                    'mahasiswa' => $mahasiswa_list, // Daftar mahasiswa
                ],
                'message' => 'Daftar mahasiswa berhasil diambil.'
            ], 200);
        } catch (\Exception $e) {
            // Tangani error umum (misalnya query DB gagal)
            // \Log::error('Error fetching student list for grading: ', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan internal server.',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null // Jangan kirim stack trace ke production
            ], 500);
        }
    }
}
