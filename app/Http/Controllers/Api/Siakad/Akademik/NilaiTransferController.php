<?php

namespace App\Http\Controllers\Api\Siakad\Akademik;

use App\Http\Controllers\Controller;
use App\Models\Akademik\NilaiTransfer;
use App\Models\MasterData\Mahasiswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class NilaiTransferController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $authenticatedMahasiswa = $this->getAuthenticatedMahasiswa($request);

        if ($authenticatedMahasiswa) {
            $request->merge([
                'id_mahasiswa' => $authenticatedMahasiswa->id,
            ]);
        }

        $query = NilaiTransfer::with([
            'mahasiswa:id,nim,nama_mahasiswa,angkatan,id_prodi,jenis_pendaftaran',
            'mataKuliah:id,kode_mk,nama_mk,sks',
        ])->orderBy('created_at', 'desc');

        if ($request->filled('id_mahasiswa')) {
            $query->where('id_mahasiswa', $request->id_mahasiswa);
        }

        if ($request->filled('sync_status')) {
            $query->where('sync_status', $request->sync_status);
        }

        if ($request->filled('q')) {
            $search = trim((string) $request->q);
            $query->where(function ($q) use ($search) {
                $q->where('nama_mata_kuliah_asal', 'like', "%{$search}%")
                    ->orWhere('kode_mata_kuliah_asal', 'like', "%{$search}%")
                    ->orWhereHas('mataKuliah', function ($sub) use ($search) {
                        $sub->where('nama_mk', 'like', "%{$search}%")
                            ->orWhere('kode_mk', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = (int) $request->get('per_page', 25);
        $data = $perPage > 0 ? $query->paginate($perPage) : $query->get();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function summary(Request $request, string $mahasiswaId): JsonResponse
    {
        $mahasiswa = Mahasiswa::with('prodi')->find($mahasiswaId);

        if (! $mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Data mahasiswa tidak ditemukan',
            ], 404);
        }

        $transfers = NilaiTransfer::with('mataKuliah')->where('id_mahasiswa', $mahasiswaId)->get();

        $totalSksDiakui = (float) $transfers->sum('sks_diakui');
        $totalMatakuliah = $transfers->count();
        $totalMutu = (float) $transfers->sum(function (NilaiTransfer $t) {
            return ((float) $t->sks_diakui) * ((float) ($t->nilai_indeks_diakui ?? 0));
        });

        $rataRataIndeks = $totalSksDiakui > 0 ? round($totalMutu / $totalSksDiakui, 2) : 0.0;

        return response()->json([
            'success' => true,
            'data' => [
                'mahasiswa' => $mahasiswa,
                'total_matakuliah' => $totalMatakuliah,
                'total_sks_diakui' => $totalSksDiakui,
                'total_mutu' => $totalMutu,
                'rata_rata_indeks' => $rataRataIndeks,
                'items' => $transfers,
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $transfer = NilaiTransfer::with([
            'mahasiswa:id,nim,nama_mahasiswa,angkatan,id_prodi',
            'mataKuliah:id,kode_mk,nama_mk,sks',
        ])->find($id);

        if (! $transfer) {
            return response()->json([
                'success' => false,
                'message' => 'Data konversi nilai transfer tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $transfer,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_mahasiswa' => 'required|uuid|exists:mahasiswa,id',
            'id_mata_kuliah' => 'required|uuid|exists:mata_kuliah,id',
            'kode_mata_kuliah_asal' => 'nullable|string|max:50',
            'nama_mata_kuliah_asal' => 'required|string|max:150',
            'sks_asal' => 'nullable|numeric|min:0|max:20',
            'nilai_huruf_asal' => 'nullable|string|max:5',
            'sks_diakui' => 'required|numeric|min:0.5|max:20',
            'nilai_angka_diakui' => 'nullable|numeric|min:0|max:100',
            'nilai_huruf_diakui' => 'required|string|max:5',
            'nilai_indeks_diakui' => 'required|numeric|min:0|max:4',
            'keterangan' => 'nullable|string|max:1000',
        ], [
            'id_mahasiswa.required' => 'Mahasiswa wajib dipilih',
            'id_mata_kuliah.required' => 'Mata kuliah penyetaraan wajib dipilih',
            'nama_mata_kuliah_asal.required' => 'Nama mata kuliah asal wajib diisi',
            'sks_diakui.required' => 'SKS diakui wajib diisi',
            'nilai_huruf_diakui.required' => 'Nilai huruf diakui wajib diisi',
            'nilai_indeks_diakui.required' => 'Nilai indeks mutu diakui wajib diisi',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Cek duplikasi penyetaraan mata kuliah yang sama untuk mahasiswa yang sama
        $exists = NilaiTransfer::where('id_mahasiswa', $request->id_mahasiswa)
            ->where('id_mata_kuliah', $request->id_mata_kuliah)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Mata kuliah penyetaraan ini sudah terdaftar dalam konversi nilai mahasiswa',
            ], 422);
        }

        try {
            $transfer = DB::transaction(function () use ($request) {
                $item = NilaiTransfer::create([
                    'id_mahasiswa' => $request->id_mahasiswa,
                    'id_mata_kuliah' => $request->id_mata_kuliah,
                    'kode_mata_kuliah_asal' => $request->kode_mata_kuliah_asal,
                    'nama_mata_kuliah_asal' => $request->nama_mata_kuliah_asal,
                    'sks_asal' => $request->sks_asal ?? $request->sks_diakui,
                    'nilai_huruf_asal' => $request->nilai_huruf_asal ?? $request->nilai_huruf_diakui,
                    'sks_diakui' => $request->sks_diakui,
                    'nilai_angka_diakui' => $request->nilai_angka_diakui,
                    'nilai_huruf_diakui' => strtoupper(trim((string) $request->nilai_huruf_diakui)),
                    'nilai_indeks_diakui' => $request->nilai_indeks_diakui,
                    'keterangan' => $request->keterangan,
                    'created_by' => $request->user()?->id,
                ]);

                // Sinkronkan total sks_diakui pada profil mahasiswa
                $totalSks = NilaiTransfer::where('id_mahasiswa', $request->id_mahasiswa)->sum('sks_diakui');
                Mahasiswa::where('id', $request->id_mahasiswa)->update([
                    'sks_diakui' => $totalSks,
                ]);

                return $item;
            });

            return response()->json([
                'success' => true,
                'message' => 'Konversi nilai transfer berhasil disimpan',
                'data' => $transfer->load('mataKuliah'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan konversi nilai: '.$e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $transfer = NilaiTransfer::find($id);

        if (! $transfer) {
            return response()->json([
                'success' => false,
                'message' => 'Data konversi nilai transfer tidak ditemukan',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'id_mata_kuliah' => 'required|uuid|exists:mata_kuliah,id',
            'kode_mata_kuliah_asal' => 'nullable|string|max:50',
            'nama_mata_kuliah_asal' => 'required|string|max:150',
            'sks_asal' => 'nullable|numeric|min:0|max:20',
            'nilai_huruf_asal' => 'nullable|string|max:5',
            'sks_diakui' => 'required|numeric|min:0.5|max:20',
            'nilai_angka_diakui' => 'nullable|numeric|min:0|max:100',
            'nilai_huruf_diakui' => 'required|string|max:5',
            'nilai_indeks_diakui' => 'required|numeric|min:0|max:4',
            'keterangan' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Cek jika ganti mata kuliah penyetaraan tidak boleh duplikat dengan baris lain
        $duplicate = NilaiTransfer::where('id_mahasiswa', $transfer->id_mahasiswa)
            ->where('id_mata_kuliah', $request->id_mata_kuliah)
            ->where('id', '!=', $id)
            ->exists();

        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => 'Mata kuliah penyetaraan ini sudah terdaftar dalam konversi nilai mahasiswa',
            ], 422);
        }

        try {
            DB::transaction(function () use ($transfer, $request) {
                $transfer->update([
                    'id_mata_kuliah' => $request->id_mata_kuliah,
                    'kode_mata_kuliah_asal' => $request->kode_mata_kuliah_asal,
                    'nama_mata_kuliah_asal' => $request->nama_mata_kuliah_asal,
                    'sks_asal' => $request->sks_asal ?? $request->sks_diakui,
                    'nilai_huruf_asal' => $request->nilai_huruf_asal ?? $request->nilai_huruf_diakui,
                    'sks_diakui' => $request->sks_diakui,
                    'nilai_angka_diakui' => $request->nilai_angka_diakui,
                    'nilai_huruf_diakui' => strtoupper(trim((string) $request->nilai_huruf_diakui)),
                    'nilai_indeks_diakui' => $request->nilai_indeks_diakui,
                    'keterangan' => $request->keterangan,
                ]);

                // Sinkronkan total sks_diakui pada profil mahasiswa
                $totalSks = NilaiTransfer::where('id_mahasiswa', $transfer->id_mahasiswa)->sum('sks_diakui');
                Mahasiswa::where('id', $transfer->id_mahasiswa)->update([
                    'sks_diakui' => $totalSks,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Konversi nilai transfer berhasil diperbarui',
                'data' => $transfer->fresh('mataKuliah'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui konversi nilai: '.$e->getMessage(),
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        $transfer = NilaiTransfer::find($id);

        if (! $transfer) {
            return response()->json([
                'success' => false,
                'message' => 'Data konversi nilai transfer tidak ditemukan',
            ], 404);
        }

        try {
            $mahasiswaId = $transfer->id_mahasiswa;

            DB::transaction(function () use ($transfer, $mahasiswaId) {
                $transfer->delete();

                // Sinkronkan total sks_diakui pada profil mahasiswa
                $totalSks = NilaiTransfer::where('id_mahasiswa', $mahasiswaId)->sum('sks_diakui');
                Mahasiswa::where('id', $mahasiswaId)->update([
                    'sks_diakui' => $totalSks,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Konversi nilai transfer berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus konversi nilai: '.$e->getMessage(),
            ], 500);
        }
    }

    private function getAuthenticatedMahasiswa(Request $request): ?Mahasiswa
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        return Mahasiswa::where('user_id', $user->id)->first();
    }
}
