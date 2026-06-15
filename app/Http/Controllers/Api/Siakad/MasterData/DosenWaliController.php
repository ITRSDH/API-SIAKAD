<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\Mahasiswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DosenWaliController extends Controller
{
    /**
     * 1. DAFTAR DOSEN WALI AKTIF
     * Menampilkan dosen yang sudah membimbing minimal 1 mahasiswa.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // whereHas memastikan dosen memiliki minimal 1 relasi di tabel mahasiswa
            $dosenWali = Dosen::whereHas('mahasiswaWali')
                ->withCount('mahasiswaWali as total_bimbingan')
                ->orderBy('nama_dosen')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar dosen wali aktif dimuat',
                'data'    => $dosenWali
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getMahasiswa(Request $request): JsonResponse
    {
        try {
            $nama       = $request->nama;
            $angkatan   = $request->angkatan;
            $prodiId    = $request->id_prodi;
            $perPage    = $request->get('per_page', 10);
            $page       = $request->get('page', 1);

            $mahasiswa = Mahasiswa::with('prodi')
                ->whereDoesntHave('dosenWali')

                // 🔍 Filter gabungan (nim + nama)
                ->when($nama, function ($query) use ($nama) {
                    $query->where(function ($q) use ($nama) {
                        $q->where('nim', 'like', "%{$nama}%")
                            ->orWhere('nama_mahasiswa', 'like', "%{$nama}%");
                        $q->where('status', 'aktif');
                    });
                })

                // 🔍 Filter angkatan
                ->when($angkatan, function ($query) use ($angkatan) {
                    $query->where('angkatan', $angkatan);
                })

                // 🔍 Filter prodi
                ->when($prodiId, function ($query) use ($prodiId) {
                    $query->where('id_prodi', $prodiId);
                })

                ->paginate($perPage, ['*'], 'page', $page);

            // 🔥 Transform response
            $data = collect($mahasiswa->items())->map(function ($item) {
                return [
                    'id'        => $item->id,
                    'nama'      => $item->nim . ' - ' . $item->nama_mahasiswa,
                    'angkatan'  => $item->angkatan,
                    'prodi'     => $item->prodi?->nama_prodi,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Daftar mahasiswa wali dimuat',
                'data'    => $data,
                'meta'    => [
                    'current_page' => $mahasiswa->currentPage(),
                    'last_page'    => $mahasiswa->lastPage(),
                    'per_page'     => $mahasiswa->perPage(),
                    'total'        => $mahasiswa->total(),
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show detailed information about a specific Dosen Wali
     */
    public function detail($id): JsonResponse
    {
        try {
            $dosenWali = Dosen::with(['mahasiswaWali' => function ($query) {
                $query->with('prodi')
                    ->orderBy('nama_mahasiswa');
            }])
                ->withCount('mahasiswaWali as total_bimbingan')
                ->findOrFail($id);

            // Transform mahasiswa data
            $mahasiswaList = $dosenWali->mahasiswaWali->map(function ($mahasiswa) {
                return [
                    'id'            => $mahasiswa->id,
                    'nim'           => $mahasiswa->nim,
                    'nama_mahasiswa' => $mahasiswa->nama_mahasiswa,
                    'angkatan'      => $mahasiswa->angkatan,
                    'prodi'         => $mahasiswa->prodi?->nama_prodi,
                ];
            });

            $data = [
                'id'            => $dosenWali->id,
                'nidn'          => $dosenWali->nidn,
                'nama_dosen'    => $dosenWali->nama_dosen,
                'email'         => $dosenWali->email,
                'total_bimbingan' => $dosenWali->total_bimbingan,
                'mahasiswa'     => $mahasiswaList
            ];

            return response()->json([
                'success' => true,
                'message' => 'Detail dosen wali dimuat',
                'data'    => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 2. ASSIGN DOSEN WALI (Bulk Update)
     */
    public function assign(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_dosen'      => 'required|exists:dosen,id',
            'mahasiswa_ids' => 'required|array|min:1',
            'mahasiswa_ids.*' => 'exists:mahasiswa,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $count = Mahasiswa::whereIn('id', $request->mahasiswa_ids)
                ->update(['id_dosen' => $request->id_dosen]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil menetapkan dosen wali untuk {$count} mahasiswa.",
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 3. TRANSFER DOSEN WALI (Pindahkan mahasiswa dari dosen A ke dosen B)
     */
    public function unassign(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_dosen_lama' => 'required|exists:dosen,id',
            'id_dosen_baru' => 'required|exists:dosen,id|different:id_dosen_lama',
            'mahasiswa_ids' => 'sometimes|array|min:1',
            'mahasiswa_ids.*' => 'exists:mahasiswa,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Jika mahasiswa_ids tidak dikirim, pindahkan semua mahasiswa dari dosen lama
            if ($request->has('mahasiswa_ids')) {
                // Pindahkan mahasiswa spesifik
                $count = Mahasiswa::whereIn('id', $request->mahasiswa_ids)
                    ->where('id_dosen', $request->id_dosen_lama)
                    ->update(['id_dosen' => $request->id_dosen_baru]);
            } else {
                // Pindahkan semua mahasiswa dari dosen lama
                $count = Mahasiswa::where('id_dosen', $request->id_dosen_lama)
                    ->update(['id_dosen' => $request->id_dosen_baru]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil memindahkan {$count} mahasiswa dari dosen wali lama ke dosen wali baru.",
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 4. REMOVE DOSEN WALI (Bulk Unassign)
     */
    public function remove(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mahasiswa_ids' => 'required|array|min:1',
            'mahasiswa_ids.*' => 'exists:mahasiswa,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $count = Mahasiswa::whereIn('id', $request->mahasiswa_ids)
                ->update(['id_dosen' => null]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil melepas dosen wali dari {$count} mahasiswa."
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
