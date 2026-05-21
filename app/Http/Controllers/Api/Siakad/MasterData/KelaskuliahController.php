<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\KelasKuliah;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KelaskuliahController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Data Kelas Kuliah berhasil diambil',
                'data' => $this->formatKelasCollection($this->baseKelasQuery()->get()),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data Kelas Kuliah.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function kelasDosenSaya(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $dosen = $user ? Dosen::where('user_id', $user->id)->first() : null;

            if (!$dosen) {
                return response()->json([
                    'success' => true,
                    'message' => 'Profil dosen tidak ditemukan atau belum terhubung ke kelas pengajaran.',
                    'data' => [],
                ], 200);
            }

            $kelasKuliah = $this->baseKelasQuery()
                ->whereHas('dosen_pengajar', function ($query) use ($dosen) {
                    $query->where('id_registrasi_dosen', $dosen->id);
                })
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data kelas dosen berhasil diambil',
                'data' => $this->formatKelasCollection($kelasKuliah),
                'meta' => [
                    'dosen' => [
                        'id' => $dosen->id,
                        'nama_dosen' => $dosen->nama_dosen,
                        'nup' => $dosen->nup,
                    ],
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data kelas dosen.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $kelaskuliah = Kelaskuliah::select([
                'id',
                'id_prodi',
                'id_kurikulum_mata_kuliah',
                'id_semester',
                'nama_kelas',
                'kapasitas_peserta',
                'bahasan',
                'lingkup',
                'mode_kuliah',
                'tanggal_mulai_efektif',
                'tanggal_akhir_efektif',
            ])
                ->with([
                    'prodi:id,nama_prodi,jenjang_pendidikan',
                    'semester.tahunAkademik:id,tahun_akademik',
                    'kurikulumMataKuliah.mataKuliah:id,kode_mk,nama_mk,sks,sks_tatap_muka,sks_praktikum,sks_praktek_lapangan,sks_simulasi'
                ])
                ->findOrFail($id);

            $mataKuliah = $kelaskuliah->kurikulumMataKuliah->mataKuliah ?? null;

            $data = [
                'id' => $kelaskuliah->id,
                'id_prodi' => $kelaskuliah->id_prodi,
                'id_kurikulum_mata_kuliah' => $kelaskuliah->id_kurikulum_mata_kuliah,
                'id_semester' => $kelaskuliah->id_semester,
                'nama_kelas' => $kelaskuliah->nama_kelas,
                'kapasitas_peserta' => $kelaskuliah->kapasitas_peserta,
                'peserta_terdaftar' => $kelaskuliah->peserta_terdaftar_count,
                'bahasan' => $kelaskuliah->bahasan,
                'lingkup' => $kelaskuliah->lingkup,
                'mode_kuliah' => $kelaskuliah->mode_kuliah,
                'tanggal_mulai_efektif' => $kelaskuliah->tanggal_mulai_efektif,
                'tanggal_akhir_efektif' => $kelaskuliah->tanggal_akhir_efektif,

                'prodi' => $kelaskuliah->prodi
                    ? "({$kelaskuliah->prodi->jenjang_pendidikan}) {$kelaskuliah->prodi->nama_prodi}"
                    : null,

                'semester' => $kelaskuliah->semester
                    ? $kelaskuliah->semester->tahunAkademik->tahun_akademik . ' ' .
                    $kelaskuliah->semester->nama_semester
                    : null,

                // ✅ TAMBAHAN MATA KULIAH
                'mata_kuliah' => $mataKuliah ? [
                    'id' => $mataKuliah->id,
                    'kode_mk' => $mataKuliah->kode_mk,
                    'nama_mk' => $mataKuliah->nama_mk,
                    'sks' => $mataKuliah->sks,
                    'sks_tatap_muka' => $mataKuliah->sks_tatap_muka,
                    'sks_praktikum' => $mataKuliah->sks_praktikum,
                    'sks_praktek_lapangan' => $mataKuliah->sks_praktek_lapangan,
                    'sks_simulasi' => $mataKuliah->sks_simulasi,
                ] : null,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Data Kelas Kuliah berhasil diambil',
                'data' => $data,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data Kelas Kuliah.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'id_prodi' => 'required|uuid|exists:prodi,id',
            'id_kurikulum_mata_kuliah' => 'required|uuid|exists:kurikulum_mata_kuliah,id',
            'id_semester' => 'required|uuid|exists:semester,id',
            'nama_kelas' => 'required|string|max:255',
            'kapasitas_peserta' => 'nullable|integer|min:1',
            'bahasan' => 'nullable|string|max:255',
            'lingkup' => 'nullable|in:internal,eksternal,campuran',
            'mode_kuliah' => 'nullable|in:offline,online,campuran',
            'tanggal_mulai_efektif' => 'nullable|date',
            'tanggal_akhir_efektif' => 'nullable|date|after_or_equal:tanggal_mulai_efektif',
        ]);

        try {
            DB::beginTransaction();
            $kelaskuliah = Kelaskuliah::create($validatedData);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Data Kelas Kuliah berhasil ditambahkan',
                'data' => $kelaskuliah,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat kelas kuliah',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $kelasKuliah = Kelaskuliah::findOrFail($id);

        $validatedData = $request->validate([
            'id_prodi' => 'required|uuid|exists:prodi,id',
            'id_kurikulum_mata_kuliah' => 'required|uuid|exists:kurikulum_mata_kuliah,id',
            'id_semester' => 'required|uuid|exists:semester,id',
            'nama_kelas' => 'required|string|max:255',
            'kapasitas_peserta' => 'nullable|integer|min:1',
            'bahasan' => 'nullable|string|max:255',
            'lingkup' => 'nullable|in:internal,eksternal,campuran',
            'mode_kuliah' => 'nullable|in:offline,online,campuran',
            'tanggal_mulai_efektif' => 'nullable|date',
            'tanggal_akhir_efektif' => 'nullable|date|after_or_equal:tanggal_mulai_efektif',
        ]);

        try {
            DB::beginTransaction();
            $kelasKuliah->update($validatedData);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Data Kelas Kuliah berhasil diupdate',
                'data' => $kelasKuliah,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate kelas kuliah',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        $kelasKuliah = Kelaskuliah::findOrFail($id);

        if (!$kelasKuliah) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas kuliah not found'
            ], 404);
        }

        try {
            $kelasKuliah->delete();

            return response()->json([
                'success' => true,
                'message' => 'Kelas kuliah berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus kelas kuliah',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function baseKelasQuery()
    {
        return Kelaskuliah::select([
            'id',
            'id_prodi',
            'id_kurikulum_mata_kuliah',
            'id_semester',
            'nama_kelas',
            'kapasitas_peserta',
            'bahasan',
            'lingkup',
            'mode_kuliah',
            'tanggal_mulai_efektif',
            'tanggal_akhir_efektif',
        ])->with([
            'prodi:id,nama_prodi,jenjang_pendidikan',
            'semester.tahunAkademik:id,tahun_akademik',
            'kurikulumMataKuliah.mataKuliah:id,kode_mk,nama_mk,sks',
            'dosen_pengajar.dosen:id,nama_dosen,nup',
        ]);
    }

    private function formatKelasCollection($kelasKuliah)
    {
        return $kelasKuliah->map(function ($item) {
            $mataKuliah = $item->kurikulumMataKuliah->mataKuliah ?? null;
            $dosenPengajar = $item->dosen_pengajar
                ->map(function ($pengajar) {
                    return [
                        'id' => $pengajar->id,
                        'urutan' => $pengajar->urutan,
                        'dosen' => $pengajar->dosen ? [
                            'id' => $pengajar->dosen->id,
                            'nama_dosen' => $pengajar->dosen->nama_dosen,
                            'nup' => $pengajar->dosen->nup,
                        ] : null,
                    ];
                })
                ->values();

            return [
                'id' => $item->id,
                'id_prodi' => $item->id_prodi,
                'id_kurikulum_mata_kuliah' => $item->id_kurikulum_mata_kuliah,
                'id_semester' => $item->id_semester,
                'nama_kelas' => $item->nama_kelas,
                'kapasitas_peserta' => $item->kapasitas_peserta,
                'peserta_terdaftar' => $item->peserta_terdaftar_count,
                'bahasan' => $item->bahasan,
                'lingkup' => $item->lingkup,
                'mode_kuliah' => $item->mode_kuliah,
                'tanggal_mulai_efektif' => $item->tanggal_mulai_efektif,
                'tanggal_akhir_efektif' => $item->tanggal_akhir_efektif,
                'prodi' => $item->prodi
                    ? "({$item->prodi->jenjang_pendidikan}) {$item->prodi->nama_prodi}"
                    : null,
                'semester' => $item->semester
                    ? $item->semester->tahunAkademik->tahun_akademik . ' ' . $item->semester->nama_semester
                    : null,
                'mata_kuliah' => $mataKuliah ? [
                    'id' => $mataKuliah->id,
                    'kode_mk' => $mataKuliah->kode_mk,
                    'nama_mk' => $mataKuliah->nama_mk,
                    'sks' => $mataKuliah->sks,
                ] : null,
                'dosen_pengajar' => $dosenPengajar,
            ];
        })->values();
    }
}
