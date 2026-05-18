<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\DosenPengajarKelas;
use App\Models\MasterData\JadwalKuliah;
use App\Models\MasterData\KelasKuliah;
use App\Models\MasterData\RuangKuliah;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class JadwalKuliahController extends Controller
{
    public function index(string $id_kelas_kuliah): JsonResponse
    {
        try {
            $jadwalKuliah = JadwalKuliah::select([
                'id',
                'id_kelas_kuliah',
                'id_ruang',
                'hari',
                'jam_mulai',
                'jam_selesai'
            ])
                ->with([
                    'kelas:id,nama_kelas,id_prodi',
                    'ruang:id,kode_ruang,nama_ruang,gedung,lantai,kapasitas,is_active'
                ])
                ->where('id_kelas_kuliah', $id_kelas_kuliah)
                ->get();

            $data = $jadwalKuliah->map(function ($item) {
                return [
                    'id' => $item->id,
                    'id_kelas_kuliah' => $item->id_kelas_kuliah,
                    'id_ruang' => $item->id_ruang,
                    'hari' => $item->hari,
                    'jam_mulai' => $item->jam_mulai,
                    'jam_selesai' => $item->jam_selesai,
                    'kelas' => $item->kelas,
                    'ruang' => $item->ruang,
                ];
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Data jadwal kuliah berhasil diambil',
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil data jadwal kuliah',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $jadwalKuliah = JadwalKuliah::select([
                'id',
                'id_kelas_kuliah',
                'id_ruang',
                'hari',
                'jam_mulai',
                'jam_selesai'
            ])
                ->with([
                    'kelas:id,nama_kelas,id_prodi',
                    'ruang:id,kode_ruang,nama_ruang,gedung,lantai,kapasitas,is_active'
                ])
                ->findOrFail($id);

            $data = [
                'id' => $jadwalKuliah->id,
                'id_kelas_kuliah' => $jadwalKuliah->id_kelas_kuliah,
                'id_ruang' => $jadwalKuliah->id_ruang,
                'hari' => $jadwalKuliah->hari,
                'jam_mulai' => $jadwalKuliah->jam_mulai,
                'jam_selesai' => $jadwalKuliah->jam_selesai,
                'kelas' => $jadwalKuliah->kelas,
                'ruang' => $jadwalKuliah->ruang,
            ];

            return response()->json([
                'status' => 'success',
                'message' => 'Detail jadwal kuliah berhasil diambil',
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data jadwal kuliah tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function store(Request $request, string $id_kelas_kuliah): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_ruang' => 'nullable|uuid|exists:ruang_kuliah,id',
            'hari' => 'nullable|string|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu',
            'jam_mulai' => 'nullable|date_format:H:i:s',
            'jam_selesai' => 'nullable|date_format:H:i:s|after:jam_mulai'
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

            $capacityError = $this->validateRoomCapacity($id_kelas_kuliah, $request->id_ruang);
            if ($capacityError) {
                DB::rollBack();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Kapasitas ruang tidak mencukupi untuk kelas ini',
                    'data' => $capacityError,
                ], 422);
            }

            $conflict = $this->findRoomConflict(
                $request->id_ruang,
                $request->hari,
                $request->jam_mulai,
                $request->jam_selesai
            );

            if ($conflict) {
                DB::rollBack();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Ruang kuliah sudah dipakai pada hari dan jam tersebut',
                    'data' => $this->transformConflict($conflict),
                ], 422);
            }

            $dosenConflict = $this->findLecturerConflict(
                $id_kelas_kuliah,
                $request->hari,
                $request->jam_mulai,
                $request->jam_selesai
            );

            if ($dosenConflict) {
                DB::rollBack();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Dosen pengajar bentrok dengan jadwal kelas lain',
                    'data' => $this->transformLecturerConflict($dosenConflict),
                ], 422);
            }

            $jadwalKuliah = JadwalKuliah::create([
                'id' => (string) Str::uuid(),
                'id_kelas_kuliah' => $id_kelas_kuliah,
                'id_ruang' => $request->id_ruang,
                'hari' => $request->hari,
                'jam_mulai' => $request->jam_mulai,
                'jam_selesai' => $request->jam_selesai,
            ]);

            $jadwalKuliah->load([
                'kelas:id,nama_kelas,id_prodi',
                'ruang:id,kode_ruang,nama_ruang,gedung,lantai,kapasitas,is_active'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Jadwal kuliah berhasil ditambahkan',
                'data' => $jadwalKuliah
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menambahkan jadwal kuliah',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_ruang' => 'nullable|uuid|exists:ruang_kuliah,id',
            'hari' => 'nullable|string|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu',
            'jam_mulai' => 'nullable|date_format:H:i:s',
            'jam_selesai' => 'nullable|date_format:H:i:s|after:jam_mulai'
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

            $jadwalKuliah = JadwalKuliah::findOrFail($id);
            $payload = $request->only([
                'id_ruang',
                'hari',
                'jam_mulai',
                'jam_selesai',
            ]);

            $idRuang = array_key_exists('id_ruang', $payload) ? $payload['id_ruang'] : $jadwalKuliah->id_ruang;
            $hari = array_key_exists('hari', $payload) ? $payload['hari'] : $jadwalKuliah->hari;
            $jamMulai = array_key_exists('jam_mulai', $payload) ? $payload['jam_mulai'] : $jadwalKuliah->jam_mulai;
            $jamSelesai = array_key_exists('jam_selesai', $payload) ? $payload['jam_selesai'] : $jadwalKuliah->jam_selesai;

            $capacityError = $this->validateRoomCapacity($jadwalKuliah->id_kelas_kuliah, $idRuang);
            if ($capacityError) {
                DB::rollBack();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Kapasitas ruang tidak mencukupi untuk kelas ini',
                    'data' => $capacityError,
                ], 422);
            }

            $conflict = $this->findRoomConflict(
                $idRuang,
                $hari,
                $jamMulai,
                $jamSelesai,
                $jadwalKuliah->id
            );

            if ($conflict) {
                DB::rollBack();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Ruang kuliah sudah dipakai pada hari dan jam tersebut',
                    'data' => $this->transformConflict($conflict),
                ], 422);
            }

            $dosenConflict = $this->findLecturerConflict(
                $jadwalKuliah->id_kelas_kuliah,
                $hari,
                $jamMulai,
                $jamSelesai,
                $jadwalKuliah->id
            );

            if ($dosenConflict) {
                DB::rollBack();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Dosen pengajar bentrok dengan jadwal kelas lain',
                    'data' => $this->transformLecturerConflict($dosenConflict),
                ], 422);
            }

            $jadwalKuliah->update($payload);

            $jadwalKuliah->load([
                'kelas:id,nama_kelas,id_prodi',
                'ruang:id,kode_ruang,nama_ruang,gedung,lantai,kapasitas,is_active'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Jadwal kuliah berhasil diperbarui',
                'data' => $jadwalKuliah
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui jadwal kuliah',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $jadwalKuliah = JadwalKuliah::findOrFail($id);
            $jadwalKuliah->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Jadwal kuliah berhasil dihapus'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menghapus jadwal kuliah',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function findRoomConflict(
        ?string $idRuang,
        ?string $hari,
        ?string $jamMulai,
        ?string $jamSelesai,
        ?string $excludeJadwalId = null
    ): ?JadwalKuliah {
        if (!$idRuang || !$hari || !$jamMulai || !$jamSelesai) {
            return null;
        }

        return JadwalKuliah::with([
            'kelas:id,nama_kelas,id_prodi',
            'ruang:id,kode_ruang,nama_ruang,gedung,lantai,kapasitas,is_active'
        ])
            ->where('id_ruang', $idRuang)
            ->where('hari', $hari)
            ->when($excludeJadwalId, function ($query) use ($excludeJadwalId) {
                $query->where('id', '!=', $excludeJadwalId);
            })
            ->where(function ($query) use ($jamMulai, $jamSelesai) {
                $query->where('jam_mulai', '<', $jamSelesai)
                    ->where('jam_selesai', '>', $jamMulai);
            })
            ->first();
    }

    private function transformConflict(JadwalKuliah $jadwalKuliah): array
    {
        return [
            'id' => $jadwalKuliah->id,
            'id_kelas_kuliah' => $jadwalKuliah->id_kelas_kuliah,
            'id_ruang' => $jadwalKuliah->id_ruang,
            'hari' => $jadwalKuliah->hari,
            'jam_mulai' => $jadwalKuliah->jam_mulai,
            'jam_selesai' => $jadwalKuliah->jam_selesai,
            'kelas' => $jadwalKuliah->kelas,
            'ruang' => $jadwalKuliah->ruang,
        ];
    }

    private function findLecturerConflict(
        string $idKelasKuliah,
        ?string $hari,
        ?string $jamMulai,
        ?string $jamSelesai,
        ?string $excludeJadwalId = null
    ): ?array {
        if (!$hari || !$jamMulai || !$jamSelesai) {
            return null;
        }

        $kelas = KelasKuliah::with(['dosen_pengajar.dosen'])->find($idKelasKuliah);
        if (!$kelas || $kelas->dosen_pengajar->isEmpty()) {
            return null;
        }

        $lecturerIds = $kelas->dosen_pengajar
            ->pluck('id_registrasi_dosen')
            ->filter()
            ->unique()
            ->values();

        if ($lecturerIds->isEmpty()) {
            return null;
        }

        $conflict = JadwalKuliah::with([
            'kelas:id,nama_kelas,id_prodi',
            'kelas.dosen_pengajar.dosen:id,nama_dosen,nidn',
            'ruang:id,kode_ruang,nama_ruang,gedung,lantai,kapasitas,is_active'
        ])
            ->where('id_kelas_kuliah', '!=', $idKelasKuliah)
            ->where('hari', $hari)
            ->when($excludeJadwalId, function ($query) use ($excludeJadwalId) {
                $query->where('id', '!=', $excludeJadwalId);
            })
            ->where(function ($query) use ($jamMulai, $jamSelesai) {
                $query->where('jam_mulai', '<', $jamSelesai)
                    ->where('jam_selesai', '>', $jamMulai);
            })
            ->whereHas('kelas.dosen_pengajar', function ($query) use ($lecturerIds) {
                $query->whereIn('id_registrasi_dosen', $lecturerIds);
            })
            ->first();

        if (!$conflict) {
            return null;
        }

        $matchingLecturers = $conflict->kelas->dosen_pengajar
            ->filter(fn($item) => $lecturerIds->contains($item->id_registrasi_dosen))
            ->map(fn($item) => [
                'id' => $item->dosen?->id,
                'nidn' => $item->dosen?->nidn,
                'nama_dosen' => $item->dosen?->nama_dosen,
            ])
            ->values();

        return [
            'jadwal' => $conflict,
            'dosen' => $matchingLecturers,
        ];
    }

    private function transformLecturerConflict(array $conflict): array
    {
        /** @var JadwalKuliah $jadwalKuliah */
        $jadwalKuliah = $conflict['jadwal'];

        return [
            'jadwal' => [
                'id' => $jadwalKuliah->id,
                'id_kelas_kuliah' => $jadwalKuliah->id_kelas_kuliah,
                'id_ruang' => $jadwalKuliah->id_ruang,
                'hari' => $jadwalKuliah->hari,
                'jam_mulai' => $jadwalKuliah->jam_mulai,
                'jam_selesai' => $jadwalKuliah->jam_selesai,
                'kelas' => $jadwalKuliah->kelas,
                'ruang' => $jadwalKuliah->ruang,
            ],
            'dosen' => $conflict['dosen'],
        ];
    }

    private function validateRoomCapacity(string $idKelasKuliah, ?string $idRuang): ?array
    {
        if (!$idRuang) {
            return null;
        }

        $kelas = KelasKuliah::select('id', 'nama_kelas', 'kapasitas_peserta')
            ->find($idKelasKuliah);
        $ruang = RuangKuliah::select('id', 'kode_ruang', 'nama_ruang', 'kapasitas')
            ->find($idRuang);

        if (!$kelas || !$ruang || $kelas->kapasitas_peserta === null) {
            return null;
        }

        if ($ruang->kapasitas >= $kelas->kapasitas_peserta) {
            return null;
        }

        return [
            'kelas' => [
                'id' => $kelas->id,
                'nama_kelas' => $kelas->nama_kelas,
                'kapasitas_peserta' => $kelas->kapasitas_peserta,
            ],
            'ruang' => [
                'id' => $ruang->id,
                'kode_ruang' => $ruang->kode_ruang,
                'nama_ruang' => $ruang->nama_ruang,
                'kapasitas' => $ruang->kapasitas,
            ],
        ];
    }
}
