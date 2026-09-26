<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\GenerateKelasKuliahCandidateRequest;
use App\Http\Requests\MasterData\GenerateKelasKuliahCreateRequest;
use App\Http\Requests\MasterData\RegisterKrsRequest;
use App\Http\Requests\MasterData\StoreKelasKuliahRequest;
use App\Http\Requests\MasterData\UpdateKelasKuliahRequest;
use App\Models\Akademik\KRS;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\KelasKuliah;
use App\Models\MasterData\Mahasiswa;
use App\Services\KelasKuliahGenerationService;
use App\Services\KelasKuliahService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KelasKuliahController extends Controller
{
    public function __construct(
        private readonly KelasKuliahService $kelasKuliahService,
        private readonly KelasKuliahGenerationService $kelasKuliahGenerationService
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
                    'kurikulumMataKuliah' => function ($query) {
                        $query->select('id', 'id_kurikulum', 'id_mata_kuliah', 'semester_ke', 'status_mk', 'is_wajib')
                            ->with([
                                'mataKuliah:id,kode_mk,nama_mk,sks,sks_tatap_muka,sks_praktikum,sks_praktek_lapangan,sks_simulasi',
                                'kurikulum:id,nama_struktur_mk',
                            ]);
                    },
                ])
                ->findOrFail($id);

            $mataKuliah = $kelaskuliah->kurikulumMataKuliah->mataKuliah ?? null;
            $kurikulumMK = $kelaskuliah->kurikulumMataKuliah;

            $data = [
                'id' => $kelaskuliah->id,
                'id_prodi' => $kelaskuliah->id_prodi,
                'id_kurikulum_mata_kuliah' => $kelaskuliah->id_kurikulum_mata_kuliah,
                'id_kurikulum' => $kurikulumMK?->id_kurikulum,
                'nama_kurikulum' => $kurikulumMK?->kurikulum?->nama_kurikulum ?? $kurikulumMK?->kurikulum?->nama_struktur_mk,
                'semester_ke' => $kurikulumMK?->semester_ke,
                'status_mk' => $kurikulumMK?->status_mk ?? ($kurikulumMK?->is_wajib ? 'wajib' : 'pilihan'),
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

    public function krsCandidates(Request $request, string $id): JsonResponse
    {
        try {
            $kelasKuliah = $this->kelasKuliahService->loadKelasForKrsRegistration($id);
            $targetMataKuliahId = $kelasKuliah->kurikulumMataKuliah?->id_mata_kuliah;
            $candidateSks = (int) ($kelasKuliah->kurikulumMataKuliah?->mataKuliah?->sks ?? 0);

            $status = $request->query('status', 'aktif');

            $mahasiswaQuery = Mahasiswa::query()
                ->where('id_prodi', $kelasKuliah->id_prodi)
                ->where('status', '!=', 'PMB');

            if ($status && strtolower($status) !== 'all') {
                $mahasiswaQuery->whereRaw('LOWER(status) = ?', [strtolower($status)]);
            }

            $mahasiswaItems = $mahasiswaQuery
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

    public function krsSyncPreview(string $id): JsonResponse
    {
        $kelasKuliah = KelasKuliah::with([
            'prodi',
            'kurikulumMataKuliah.mataKuliah',
            'kurikulumMataKuliah.kurikulum',
            'krsDetail.krs.mahasiswa',
            'krsDetail.mataKuliah',
        ])->find($id);

        if (! $kelasKuliah) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas kuliah tidak ditemukan',
            ], 404);
        }

        $targetKmk = $kelasKuliah->kurikulumMataKuliah;
        $targetMataKuliah = $targetKmk?->mataKuliah;
        $targetKurikulum = $targetKmk?->kurikulum;
        $targetMataKuliahId = $targetKmk?->id_mata_kuliah;

        $targetKurikulumName = $targetKurikulum?->nama_struktur_mk ?? '-';
        $isKurikulumRpl = stripos($targetKurikulumName, 'RPL') !== false;

        $krsDetails = $kelasKuliah->krsDetail;
        $pesertaList = [];
        $mismatchCount = 0;
        $matchedCount = 0;
        $regulerCount = 0;
        $rplCount = 0;

        foreach ($krsDetails as $detail) {
            $mhs = $detail->krs?->mahasiswa;
            $isMhsRpl = ($mhs?->jenis_pendaftaran === 'RPL') || (preg_match('/[Bb]$/', (string) $mhs?->nim) === 1);
            $jenisPendaftaran = $isMhsRpl ? 'RPL' : 'Reguler';

            if ($isMhsRpl) {
                $rplCount++;
            } else {
                $regulerCount++;
            }

            $currentMk = $detail->mataKuliah;
            $isMatched = ($detail->id_mata_kuliah !== null && $detail->id_mata_kuliah === $targetMataKuliahId);

            if ($isMatched) {
                $matchedCount++;
            } else {
                $mismatchCount++;
            }

            $keteranganTipe = null;
            if ($isKurikulumRpl && ! $isMhsRpl) {
                $keteranganTipe = 'Mahasiswa Reguler di Kurikulum RPL';
            } elseif (! $isKurikulumRpl && $isMhsRpl) {
                $keteranganTipe = 'Mahasiswa RPL di Kurikulum Reguler';
            }

            $pesertaList[] = [
                'id_krs_detail' => $detail->id,
                'id_mahasiswa' => $mhs?->id,
                'nim' => $mhs?->nim ?? '-',
                'nama_mahasiswa' => $mhs?->nama_mahasiswa ?? '-',
                'angkatan' => $mhs?->angkatan ?? '-',
                'jenis_pendaftaran' => $jenisPendaftaran,
                'status_mahasiswa' => $mhs?->status ?? 'Aktif',
                'current_mk_id' => $detail->id_mata_kuliah,
                'current_mk_nama' => $currentMk ? "{$currentMk->kode_mk} - {$currentMk->nama_mk}" : '(Belum Diset / NULL)',
                'is_matched' => $isMatched,
                'needs_sync' => ! $isMatched,
                'keterangan_tipe' => $keteranganTipe,
            ];
        }

        usort($pesertaList, function ($a, $b) {
            if ($a['needs_sync'] === $b['needs_sync']) {
                return strcmp($a['nim'], $b['nim']);
            }

            return $a['needs_sync'] ? -1 : 1;
        });

        return response()->json([
            'success' => true,
            'message' => 'Data pemeriksaan kurikulum peserta berhasil diambil.',
            'data' => [
                'kelas' => [
                    'id' => $kelasKuliah->id,
                    'nama_kelas' => $kelasKuliah->nama_kelas,
                    'prodi' => $kelasKuliah->prodi?->nama_prodi ?? '-',
                    'target_mata_kuliah' => [
                        'id' => $targetMataKuliahId,
                        'kode' => $targetMataKuliah?->kode_mk,
                        'nama' => $targetMataKuliah?->nama_mk,
                        'sks' => $targetMataKuliah?->sks ?? 0,
                    ],
                    'target_kurikulum' => [
                        'id' => $targetKurikulum?->id,
                        'nama' => $targetKurikulumName,
                        'is_rpl' => $isKurikulumRpl,
                    ],
                ],
                'summary' => [
                    'total_peserta' => count($pesertaList),
                    'mismatch_count' => $mismatchCount,
                    'matched_count' => $matchedCount,
                    'reguler_count' => $regulerCount,
                    'rpl_count' => $rplCount,
                ],
                'peserta' => $pesertaList,
            ],
        ], 200);
    }

    public function syncKrsPeserta(Request $request, string $id): JsonResponse
    {
        $kelasKuliah = KelasKuliah::with([
            'kurikulumMataKuliah.mataKuliah',
            'kurikulumMataKuliah.kurikulum',
            'krsDetail.krs.mahasiswa',
            'krsDetail.mataKuliah',
        ])->find($id);

        if (! $kelasKuliah) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas kuliah tidak ditemukan',
            ], 404);
        }

        $targetKmk = $kelasKuliah->kurikulumMataKuliah;
        if (! $targetKmk || ! $targetKmk->id_mata_kuliah) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas kuliah belum terhubung ke struktur kurikulum mata kuliah yang valid.',
            ], 422);
        }

        $targetMataKuliah = $targetKmk->mataKuliah;
        $targetKurikulum = $targetKmk->kurikulum;
        $targetMataKuliahId = $targetKmk->id_mata_kuliah;

        $krsDetails = $kelasKuliah->krsDetail;
        $totalPeserta = $krsDetails->count();

        if ($totalPeserta === 0) {
            return response()->json([
                'success' => true,
                'message' => 'Kelas ini belum memiliki peserta KRS terdaftar. Tidak ada data yang perlu disinkronkan.',
                'data' => [
                    'total_peserta' => 0,
                    'synced_count' => 0,
                    'mata_kuliah' => $targetMataKuliah?->nama_mk,
                    'kurikulum' => $targetKurikulum?->nama_struktur_mk,
                ],
            ], 200);
        }

        $selectedMahasiswaIds = $request->input('mahasiswa_ids', []);

        try {
            DB::beginTransaction();

            $syncedCount = 0;
            $krsIdsToRecalculate = collect();

            foreach ($krsDetails as $detail) {
                $mahasiswaId = $detail->krs?->id_mahasiswa;

                if (! empty($selectedMahasiswaIds) && is_array($selectedMahasiswaIds)) {
                    if (! in_array($mahasiswaId, $selectedMahasiswaIds, true)) {
                        continue;
                    }
                }

                $isDifferent = $detail->id_mata_kuliah !== $targetMataKuliahId;
                if ($isDifferent || empty($detail->id_mata_kuliah)) {
                    $detail->id_mata_kuliah = $targetMataKuliahId;
                    $detail->save();
                    $syncedCount++;
                }
                if ($detail->id_krs) {
                    $krsIdsToRecalculate->push($detail->id_krs);
                }
            }

            // Hitung ulang total SKS untuk setiap KRS mahasiswa yang terdampak
            $uniqueKrsIds = $krsIdsToRecalculate->unique();
            foreach ($uniqueKrsIds as $krsId) {
                $krs = KRS::find($krsId);
                if ($krs) {
                    $krs->update([
                        'total_sks' => $krs->calculateTotalSks(),
                    ]);
                }
            }

            DB::commit();

            $mkName = $targetMataKuliah?->nama_mk ?? 'Mata Kuliah';
            $kurikulumName = $targetKurikulum?->nama_struktur_mk ?? 'Kurikulum';

            return response()->json([
                'success' => true,
                'message' => "Berhasil menyinkronkan {$syncedCount} peserta ke mata kuliah {$mkName} ({$kurikulumName}).",
                'data' => [
                    'total_peserta' => $totalPeserta,
                    'synced_count' => $syncedCount,
                    'target_mata_kuliah' => [
                        'id' => $targetMataKuliahId,
                        'kode' => $targetMataKuliah?->kode_mk,
                        'nama' => $mkName,
                    ],
                    'target_kurikulum' => [
                        'id' => $targetKurikulum?->id,
                        'nama' => $kurikulumName,
                    ],
                ],
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyinkronkan kurikulum peserta kelas.',
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

    /**
     * Daftar mata kuliah calon pembuatan kelas (per prodi + kurikulum + semester_ke).
     */
    public function generateCandidates(GenerateKelasKuliahCandidateRequest $request): JsonResponse
    {
        try {
            $data = $this->kelasKuliahGenerationService->candidates($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Daftar mata kuliah calon kelas berhasil diambil.',
                'data' => $data['data'],
                'meta' => ['summary' => $data['summary']],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil daftar mata kuliah calon kelas.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Buat kelas kuliah masal untuk mata kuliah yang dipilih (satu klik).
     */
    public function generateCreate(GenerateKelasKuliahCreateRequest $request): JsonResponse
    {
        try {
            $data = $this->kelasKuliahGenerationService->create(
                $request->validated(),
                (string) auth('api')->id()
            );

            return response()->json([
                'success' => true,
                'message' => $data['message'] ?? 'Proses pembuatan kelas kuliah massal selesai.',
                'data' => $data,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat kelas kuliah massal.',
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
