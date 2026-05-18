<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\DosenPengajarKelas;
use App\Models\MasterData\JadwalKuliah;
use App\Models\MasterData\KelasKuliah;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DosenPengajarKelasController extends Controller
{
    public function index(string $id_kelas_kuliah): JsonResponse
    {
        try {
            $dosenPengajar = DosenPengajarKelas::with([
                'dosen:id,nama_dosen,nidn',
                // Pastikan foreign key ke kurikulumMataKuliah (misal: id_kurikulum_mata_kuliah) di-select
                'kelas:id,nama_kelas,id_prodi,id_kurikulum_mata_kuliah', 
                // Pastikan foreign key ke mataKuliah (misal: id_mata_kuliah) di-select
                'kelas.kurikulumMataKuliah:id,id_mata_kuliah', 
                'kelas.kurikulumMataKuliah.mataKuliah:id,sks'
            ])
            ->where('id_kelas_kuliah', $id_kelas_kuliah)
            ->get();

            if ($dosenPengajar->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Tidak ada dosen pengajar untuk kelas kuliah ini',
                    'data' => []
                ], 200);
            }

            $sksMatakuliah = $dosenPengajar->first()?->kelas?->kurikulumMataKuliah?->mataKuliah?->sks ?? 0;

            $data = $dosenPengajar->map(function ($item) {
                return [
                    'id' => $item->id,
                    'id_kelas_kuliah' => $item->id_kelas_kuliah,
                    'id_registrasi_dosen' => $item->id_registrasi_dosen,
                    'urutan' => $item->urutan,
                    'nama_dosen' => $item->dosen?->nama_dosen,
                    'nidn' => $item->dosen?->nidn,
                    'sks_substansi_total' => $item->sks_substansi_total,
                    'rencana_tatap_muka' => $item->rencana_tatap_muka,
                    'realisasi_tatap_muka' => $item->realisasi_tatap_muka,
                    'nama_kelas' => $item->kelas?->nama_kelas,
                    'id_prodi' => $item->kelas?->id_prodi,
                ];
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Data dosen pengajar kelas berhasil diambil',
                'sks_matakuliah' => $sksMatakuliah,
                'data' => $data
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $dosenPengajar = DosenPengajarKelas::select([
                'id',
                'id_kelas_kuliah',
                'id_registrasi_dosen',
                'sks_substansi_total',
                'rencana_tatap_muka',
                'realisasi_tatap_muka',
                'urutan',
            ])
                ->with([
                    'dosen:id,nama_dosen,nidn',
                    'kelas:id,nama_kelas,id_prodi'
                ])
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Detail dosen pengajar kelas berhasil diambil',
                'data' => [
                    'id' => $dosenPengajar->id,
                    'id_kelas_kuliah' => $dosenPengajar->id_kelas_kuliah,
                    'id_registrasi_dosen' => $dosenPengajar->id_registrasi_dosen,
                    'sks_substansi_total' => $dosenPengajar->sks_substansi_total,
                    'rencana_tatap_muka' => $dosenPengajar->rencana_tatap_muka,
                    'realisasi_tatap_muka' => $dosenPengajar->realisasi_tatap_muka,
                    'urutan' => $dosenPengajar->urutan,
                    'dosen' => $dosenPengajar->dosen,
                    'kelas' => $dosenPengajar->kelas,
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request, string $id_kelas_kuliah): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_registrasi_dosen' => 'required|string|exists:dosen,id',
            'sks_substansi_total' => 'nullable|numeric|min:0',
            'rencana_tatap_muka' => 'nullable|integer|min:0',
            'realisasi_tatap_muka' => 'nullable|integer|min:0',
            'urutan' => 'nullable|integer|min:1',
            // 'id_jenis_evaluasi' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Check if dosen already assigned to this kelas
            $existingAssignment = DosenPengajarKelas::where('id_kelas_kuliah', $id_kelas_kuliah)
                ->where('id_registrasi_dosen', $request->id_registrasi_dosen)
                ->first();

            if ($existingAssignment) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Dosen sudah ditugaskan pada kelas ini'
                ], 422);
            }

            $conflict = $this->findTeachingConflict(
                $request->id_registrasi_dosen,
                $id_kelas_kuliah
            );

            if ($conflict) {
                DB::rollBack();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Dosen bentrok dengan jadwal kelas lain',
                    'data' => $conflict,
                ], 422);
            }

            $dosenPengajar = DosenPengajarKelas::create([
                'id_kelas_kuliah' => $id_kelas_kuliah,
                'id_registrasi_dosen' => $request->id_registrasi_dosen,
                'sks_substansi_total' => $request->sks_substansi_total,
                'rencana_tatap_muka' => $request->rencana_tatap_muka,
                'realisasi_tatap_muka' => $request->realisasi_tatap_muka,
                'urutan' => $request->urutan,
                // 'id_jenis_evaluasi' => $request->id_jenis_evaluasi
            ]);

            $dosenPengajar->load(['dosen:id,nama_dosen,nidn', 'kelas:id,nama_kelas,id_prodi']);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Dosen pengajar kelas berhasil ditambahkan',
                'data' => $dosenPengajar
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menambahkan dosen pengajar kelas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_registrasi_dosen' => 'required|string|exists:dosen,id',
            'sks_substansi_total' => 'nullable|numeric|min:0',
            'rencana_tatap_muka' => 'nullable|integer|min:0',
            'realisasi_tatap_muka' => 'nullable|integer|min:0',
            'urutan' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $dosenPengajar = DosenPengajarKelas::findOrFail($id);
            $id_registrasi_dosen_baru = $request->id_registrasi_dosen;

            // ✅ hanya cek kalau dosennya berubah
            if ($id_registrasi_dosen_baru != $dosenPengajar->id_registrasi_dosen) {

                $exists = DosenPengajarKelas::where('id_registrasi_dosen', $id_registrasi_dosen_baru)
                    ->where('id_kelas_kuliah', $dosenPengajar->id_kelas_kuliah)
                    ->exists();

                if ($exists) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Dosen sudah ditugaskan pada kelas ini'
                    ], 422);
                }

                $conflict = $this->findTeachingConflict(
                    $id_registrasi_dosen_baru,
                    $dosenPengajar->id_kelas_kuliah
                );

                if ($conflict) {
                    DB::rollBack();

                    return response()->json([
                        'status' => 'error',
                        'message' => 'Dosen bentrok dengan jadwal kelas lain',
                        'data' => $conflict,
                    ], 422);
                }
            }

            // 🚫 Ambil hanya field yang boleh diupdate
            $dataUpdate = collect($validator->validated())
                ->only([
                    'id_registrasi_dosen',
                    'sks_substansi_total',
                    'rencana_tatap_muka',
                    'realisasi_tatap_muka',
                    'urutan'
                ])
                ->filter(fn($v) => !is_null($v))
                ->toArray();

            if (empty($dataUpdate)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tidak ada data yang diperbarui'
                ], 400);
            }

            $dosenPengajar->update($dataUpdate);

            // 🔁 relasi tetap di-load (id_kelas_kuliah & id_registrasi_dosen tetap dari DB)
            $dosenPengajar->load([
                'dosen:id,nama_dosen,nidn',
                'kelas:id,nama_kelas,id_prodi'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Dosen pengajar kelas berhasil diperbarui',
                'data' => $dosenPengajar
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat update',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $dosenPengajar = DosenPengajarKelas::findOrFail($id);

            $dosenPengajar->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Dosen pengajar kelas berhasil dihapus'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menghapus dosen pengajar kelas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function findTeachingConflict(string $dosenId, string $kelasId): ?array
    {
        $kelas = KelasKuliah::with(['jadwal', 'dosen_pengajar.dosen'])->find($kelasId);
        if (!$kelas || $kelas->jadwal->isEmpty()) {
            return null;
        }

        foreach ($kelas->jadwal as $jadwal) {
            $conflict = JadwalKuliah::with([
                'kelas:id,nama_kelas,id_prodi',
                'kelas.dosen_pengajar.dosen:id,nama_dosen,nidn',
                'ruang:id,kode_ruang,nama_ruang,gedung,lantai,kapasitas,is_active'
            ])
                ->where('id_kelas_kuliah', '!=', $kelasId)
                ->where('hari', $jadwal->hari)
                ->where(function ($query) use ($jadwal) {
                    $query->where('jam_mulai', '<', $jadwal->jam_selesai)
                        ->where('jam_selesai', '>', $jadwal->jam_mulai);
                })
                ->whereHas('kelas.dosen_pengajar', function ($query) use ($dosenId) {
                    $query->where('id_registrasi_dosen', $dosenId);
                })
                ->first();

            if ($conflict) {
                $dosen = $conflict->kelas->dosen_pengajar
                    ->firstWhere('id_registrasi_dosen', $dosenId)?->dosen;

                return [
                    'dosen' => [
                        'id' => $dosen?->id,
                        'nidn' => $dosen?->nidn,
                        'nama_dosen' => $dosen?->nama_dosen,
                    ],
                    'jadwal' => [
                        'id' => $conflict->id,
                        'id_kelas_kuliah' => $conflict->id_kelas_kuliah,
                        'hari' => $conflict->hari,
                        'jam_mulai' => $conflict->jam_mulai,
                        'jam_selesai' => $conflict->jam_selesai,
                        'kelas' => $conflict->kelas,
                        'ruang' => $conflict->ruang,
                    ],
                ];
            }
        }

        return null;
    }
}
