<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\RegisterKrsRequest;
use App\Http\Requests\MasterData\StoreKelasKuliahRequest;
use App\Http\Requests\MasterData\UpdateKelasKuliahRequest;
use App\Models\Akademik\KRS;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\KelasKuliah;
use App\Models\MasterData\Mahasiswa;
use App\Services\KelasKuliahService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KelasKuliahController extends Controller
{
    public function __construct(
        private readonly KelasKuliahService $kelasKuliahService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $query = $this->baseKelasQuery();

            if ($request->filled('id_semester')) {
                $query->where('id_semester', $request->id_semester);
            }

            if ($request->filled('id_prodi')) {
                $query->where('id_prodi', $request->id_prodi);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data Kelas Kuliah berhasil diambil',
                'data' => $this->formatKelasCollection($query->get()),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data Kelas Kuliah.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function kelasDosenSaya(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $dosen = $user ? Dosen::where('user_id', $user->id)->first() : null;

            if (! $dosen) {
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $kelaskuliah = KelasKuliah::select([
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
                    'kurikulumMataKuliah.mataKuliah:id,kode_mk,nama_mk,sks,sks_tatap_muka,sks_praktikum,sks_praktek_lapangan,sks_simulasi',
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
                    ? $kelaskuliah->semester->tahunAkademik->tahun_akademik.' '.
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function krsCandidates(string $id): JsonResponse
    {
        try {
            $kelasKuliah = $this->kelasKuliahService->loadKelasForKrsRegistration($id);
            $targetMataKuliahId = $kelasKuliah->kurikulumMataKuliah?->id_mata_kuliah;
            $candidateSks = (int) ($kelasKuliah->kurikulumMataKuliah?->mataKuliah?->sks ?? 0);

            $mahasiswaItems = Mahasiswa::query()
                ->where('id_prodi', $kelasKuliah->id_prodi)
                ->where('status', '!=', 'PMB')
                ->orderByDesc('angkatan')
                ->orderBy('nama_mahasiswa')
                ->get([
                    'id',
                    'nim',
                    'nama_mahasiswa',
                    'angkatan',
                    'status',
                ]);

            $krsByMahasiswa = KRS::query()
                ->with([
                    'details.kelasKuliah.kurikulumMataKuliah',
                    'details.kelasKuliah.jadwal',
                ])
                ->where('id_semester', $kelasKuliah->id_semester)
                ->whereIn('id_mahasiswa', $mahasiswaItems->pluck('id'))
                ->get()
                ->keyBy('id_mahasiswa');

            $repeatHistoryByMahasiswa = $this->kelasKuliahService->resolveRepeatHistoryByMahasiswa(
                $mahasiswaItems->pluck('id')->all(),
                $targetMataKuliahId,
                $kelasKuliah->id_semester
            );

            $rows = $mahasiswaItems->map(function (Mahasiswa $mahasiswa) use ($kelasKuliah, $krsByMahasiswa, $repeatHistoryByMahasiswa, $targetMataKuliahId, $candidateSks) {
                $krs = $krsByMahasiswa->get($mahasiswa->id);
                $assessment = $this->kelasKuliahService->assessMahasiswaRegistrationCandidate(
                    $mahasiswa,
                    $kelasKuliah,
                    $krs,
                    $targetMataKuliahId,
                    $candidateSks
                );
                $repeatHistory = $repeatHistoryByMahasiswa->get($mahasiswa->id);

                return [
                    'id_mahasiswa' => $mahasiswa->id,
                    'id_krs' => $krs?->id,
                    'nim' => $mahasiswa->nim,
                    'nama_mahasiswa' => $mahasiswa->nama_mahasiswa,
                    'angkatan' => $mahasiswa->angkatan,
                    'status_mahasiswa' => $mahasiswa->status,
                    'status_krs' => $krs?->status_approval,
                    'total_sks' => $krs?->total_sks ?? 0,
                    'already_registered' => $assessment['already_registered'],
                    'can_register' => $assessment['can_register'],
                    'state' => $assessment['state'],
                    'state_label' => $assessment['state_label'],
                    'state_variant' => $assessment['state_variant'],
                    'reason' => $assessment['reason'],
                    'is_repeat_candidate' => $repeatHistory !== null,
                    'repeat_history' => $repeatHistory,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Daftar calon peserta KRS berhasil diambil',
                'data' => $rows,
                'meta' => [
                    'kelas' => [
                        'id' => $kelasKuliah->id,
                        'nama_kelas' => $kelasKuliah->nama_kelas,
                        'id_semester' => $kelasKuliah->id_semester,
                        'kapasitas_peserta' => $kelasKuliah->kapasitas_peserta,
                        'peserta_terdaftar' => $kelasKuliah->peserta_terdaftar_count,
                    ],
                    'summary' => [
                        'total_mahasiswa' => $rows->count(),
                        'registered_count' => $rows->where('already_registered', true)->count(),
                        'available_count' => $rows->where('can_register', true)->count(),
                        'blocked_count' => $rows->where('can_register', false)->where('already_registered', false)->count(),
                        'repeat_count' => $rows->where('is_repeat_candidate', true)->count(),
                    ],
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil calon peserta KRS.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function registerKrsMahasiswa(RegisterKrsRequest $request, string $id): JsonResponse
    {
        try {
            $data = $this->kelasKuliahService->registerKrsMahasiswa(
                $request->validated()['mahasiswa_ids'],
                $id
            );

            return response()->json([
                'success' => true,
                'message' => 'Proses pendaftaran KRS selesai.',
                'data' => $data,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mendaftarkan mahasiswa ke KRS.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(StoreKelasKuliahRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        try {
            DB::beginTransaction();
            $kelaskuliah = KelasKuliah::create($validatedData);
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateKelasKuliahRequest $request, string $id): JsonResponse
    {
        $kelasKuliah = KelasKuliah::findOrFail($id);

        $validatedData = $request->validated();

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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        $kelasKuliah = KelasKuliah::findOrFail($id);

        if (! $kelasKuliah) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas kuliah not found',
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function baseKelasQuery()
    {
        return KelasKuliah::select([
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
                    ? $item->semester->tahunAkademik->tahun_akademik.' '.$item->semester->nama_semester
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
