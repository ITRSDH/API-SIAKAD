<?php

namespace App\Http\Controllers\Api\Siakad\DOSEN;

use Illuminate\Http\Request;
use App\Models\MasterData\Nilai;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\MasterData\Semester;
use App\Http\Controllers\Controller;
use App\Models\MasterData\KrsDetail;
use App\Models\MasterData\Mahasiswa;
use Illuminate\Support\Facades\Auth;
use App\Models\MasterData\DosenKelasMk;

class DosenMkgetmahasiswaController extends Controller
{
    public function getmahasiswa(): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();

            if (!$user || !$user->hasRole('dosen') || !$user->dosen) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak'
                ], 403);
            }

            $dosen = $user->dosen;

            // 1. Ambil semua kelas MK yang diajar dosen
            $kelasMkList = DosenKelasMk::where('id_dosen', $dosen->id)
                ->with([
                    'kelasMk.mataKuliah:id,nama_mk,kode_mk',
                    'kelasMk.kelasPararel:id,nama_kelas',
                    'kelasMk.jenisKelas:id,nama_kelas',
                ])
                ->get();

            $result = [];

            foreach ($kelasMkList as $item) {
                $kelasMk = $item->kelasMk;

                // 2. Ambil id_krs berdasarkan kelas MK
                $krsIds = KrsDetail::where('id_kelas_mk', $kelasMk->id)
                    ->pluck('id_krs');

                // 3. Ambil mahasiswa per kelas
                $mahasiswa = Mahasiswa::whereIn('id', function ($query) use ($krsIds) {
                    $query->select('id_mahasiswa')
                        ->from('krs')
                        ->whereIn('id', $krsIds);
                })
                    ->with([
                        'prodi:id,nama_prodi,kode_prodi',
                        'kelasPararel:id,nama_kelas'
                    ])
                    ->select(
                        'id',
                        'nim',
                        'nama_mahasiswa',
                        'id_prodi',
                        'id_kelas_pararel'
                    )
                    ->orderBy('nama_mahasiswa')
                    ->get();

                $result[] = [
                    'kelas_mk' => [
                        'id'       => $kelasMk->id,
                        'kode_mk'  => $kelasMk->mataKuliah->kode_mk ?? null,
                        'nama_mk'  => $kelasMk->mataKuliah->nama_mk ?? null,
                        'kelas'    => trim(
                            ($kelasMk->kelasPararel->nama_kelas ?? '') . ' ' .
                                ($kelasMk->jenisKelas->nama_kelas ?? '')
                        ),
                    ],
                    'mahasiswa' => $mahasiswa
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Daftar mahasiswa per kelas MK berhasil diambil',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan internal server',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function storeNilai(Request $request): JsonResponse
    {
        $request->validate([
            'id_kelas_mk' => 'required|uuid',
            'nilai' => 'required|array|min:1',
            'nilai.*.id_mahasiswa' => 'required|uuid',
            'nilai.*.nilai_angka' => 'required|numeric|min:0|max:100',
        ]);

        try {
            $user = Auth::guard('api')->user();

            if (!$user || !$user->hasRole('dosen') || !$user->dosen) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak'
                ], 403);
            }

            $dosen = $user->dosen;

            // 1. Validasi dosen mengajar kelas MK
            $akses = DosenKelasMk::where('id_dosen', $dosen->id)
                ->where('id_kelas_mk', $request->id_kelas_mk)
                ->exists();

            if (!$akses) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak mengajar kelas ini'
                ], 403);
            }

            // 2. Ambil semester aktif (PAKAI status = Aktif)
            $semesterAktif = Semester::where('status', 'Aktif')->first();

            if (!$semesterAktif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada semester aktif'
                ], 404);
            }

            DB::beginTransaction();

            foreach ($request->nilai as $item) {
                [$huruf, $bobot] = $this->konversiNilai($item['nilai_angka']);

                Nilai::updateOrCreate(
                    [
                        'id_kelas_mk'  => $request->id_kelas_mk,
                        'id_mahasiswa' => $item['id_mahasiswa'],
                        'id_semester'  => $semesterAktif->id,
                    ],
                    [
                        'nilai_angka' => $item['nilai_angka'],
                        'nilai_huruf' => $huruf,
                        'bobot'       => $bobot,
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Nilai mahasiswa berhasil disimpan'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan nilai',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }


    private function konversiNilai(float $nilai): array
    {
        return match (true) {
            $nilai >= 85 => ['A', 4.00],
            $nilai >= 80 => ['A-', 3.70],
            $nilai >= 75 => ['B+', 3.30],
            $nilai >= 70 => ['B', 3.00],
            $nilai >= 65 => ['B-', 2.70],
            $nilai >= 60 => ['C+', 2.30],
            $nilai >= 55 => ['C', 2.00],
            $nilai >= 40 => ['D', 1.00],
            default      => ['E', 0.00],
        };
    }
}
