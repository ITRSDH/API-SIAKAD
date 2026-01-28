<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use Illuminate\Http\Request;
use App\Models\MasterData\Dosen;
use Illuminate\Http\JsonResponse;
use App\Models\MasterData\KelasMK;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\MasterData\DosenKelasMk;
use App\Models\MasterData\JadwalKuliah;
use App\Models\MasterData\BebanAjarDosen;

class DosenMKController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $dosenMk = DosenKelasMk::with([
                'dosen:id,nama_dosen,nup',
                'kelasMk.mataKuliah:id,nama_mk,sks',
                'kelasMk.kelasPararel:id,id_prodi,nama_kelas',
                'kelasMk.kelasPararel.prodi:id,nama_prodi',
                'kelasMk.semester:id,nama_semester',
                'kelasMk.jenisKelas:id,nama_kelas',
            ])->get(['id', 'id_dosen', 'id_kelas_mk']);

            $dosenMk->transform(function ($item) {
                $item->jadwal_kuliah = JadwalKuliah::where('id_kelas_mk', $item->id_kelas_mk)
                    ->where('id_dosen', $item->id_dosen)
                    ->first();

                return $item;
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Daftar Dosen Mata Kuliah',
                'data' => $dosenMk
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function create(): JsonResponse
    {
        try {
            $kelasmk = KelasMK::select([
                'id',
                'id_mk',
                'id_kelas_pararel',
                'id_semester',
                'id_jenis_kelas',
                'kode_kelas_mk',
                'kuota',
            ])
                ->with([
                    'mataKuliah:id,nama_mk',

                    'kelasPararel:id,id_prodi,nama_kelas,angkatan',
                    'kelasPararel.prodi:id,nama_prodi',

                    'semester:id,nama_semester',
                    'jenisKelas:id,nama_kelas'
                ])
                ->get();

            $dosen = Dosen::select(['id', 'nama_dosen'])->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar Dosen',
                'data' => [
                    'dosen' => $dosen,
                    'kelasmk' => $kelasmk
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $dosenMk = DosenKelasMk::with(['dosen', 'kelasMk'])->where('id_dosen', $id)->get();

            return response()->json([
                'status' => 'success',
                'data' => $dosenMk,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_kelas_mk' => 'required|string|exists:kelas_mk,id',
            'id_dosen' => 'required|string|exists:dosen,id',
        ]);

        try {
            $dosenMk = DosenKelasMk::create([
                'id_kelas_mk' => $request->id_kelas_mk,
                'id_dosen' => $request->id_dosen,
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $dosenMk,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create record.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function edit($id)
    {
        try {
            // Ambil data dosen_kelas_mk berdasarkan ID
            $dosen_mk = DosenKelasMk::with([
                'dosen',
                'kelasMk.mataKuliah',
                'kelasMk.kelasPararel.prodi',
                'kelasMk.semester',
                'kelasMk.jenisKelas'
            ])->findOrFail($id);

            // Ambil semua data dosen untuk dropdown
            $dosen = Dosen::select(['id', 'nama_dosen'])->get();

            // Ambil semua data kelas_mk untuk dropdown
            $kelasmk = KelasMK::select([
                'id',
                'id_mk',
                'id_kelas_pararel',
                'id_semester',
                'id_jenis_kelas',
                'kode_kelas_mk',
                'kuota',
            ])
                ->with([
                    'mataKuliah:id,nama_mk',
                    'kelasPararel:id,id_prodi,nama_kelas,angkatan',
                    'kelasPararel.prodi:id,nama_prodi',
                    'semester:id,nama_semester',
                    'jenisKelas:id,nama_kelas'
                ])
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'dosen_mk' => $dosen_mk, // Satu record yang diedit
                    'dosen' => $dosen,       // Untuk dropdown dosen
                    'kelasmk' => $kelasmk    // Untuk dropdown kelas mk
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'id_kelas_mk' => 'sometimes|required|string|exists:kelas_mk,id',
            'id_dosen' => 'sometimes|required|string|exists:dosen,id',
        ]);

        try {
            $dosenMk = DosenKelasMk::findOrFail($id);

            if ($request->has('id_kelas_mk')) {
                $dosenMk->id_kelas_mk = $request->id_kelas_mk;
            }
            if ($request->has('id_dosen')) {
                $dosenMk->id_dosen = $request->id_dosen;
            }

            $dosenMk->save();

            return response()->json([
                'status' => 'success',
                'data' => $dosenMk,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update record.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $dosenMk = DosenKelasMk::findOrFail($id);

            // Ambil jadwal berdasarkan relasi data, BUKAN ID
            $jadwal = JadwalKuliah::where('id_dosen', $dosenMk->id_dosen)
                ->where('id_kelas_mk', $dosenMk->id_kelas_mk)
                ->first();

            // Hapus beban ajar (kalau ada)
            if ($jadwal) {
                BebanAjarDosen::where('id_dosen', $jadwal->id_dosen)
                    ->where('id_kelas_mk', $jadwal->id_kelas_mk)
                    ->where('id_semester', $jadwal->id_semester)
                    ->delete();

                $jadwal->delete();
            }

            // Terakhir hapus dosen kelas MK
            $dosenMk->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Data dosen, jadwal, dan beban ajar berhasil dihapus.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete record.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
