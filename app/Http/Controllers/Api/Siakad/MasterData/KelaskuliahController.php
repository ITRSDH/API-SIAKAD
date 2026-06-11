<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Akademik\KRS;
use App\Models\Akademik\KRSDetail;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\KelasKuliah;
use App\Models\MasterData\Mahasiswa;
use App\Services\CurriculumConversionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KelasKuliahController extends Controller
{
    public function __construct(
        private readonly CurriculumConversionService $curriculumConversionService
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

    public function krsCandidates(string $id): JsonResponse
    {
        try {
            $kelasKuliah = $this->loadKelasForKrsRegistration($id);
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

            $repeatHistoryByMahasiswa = $this->resolveRepeatHistoryByMahasiswa(
                $mahasiswaItems->pluck('id')->all(),
                $targetMataKuliahId,
                $kelasKuliah->id_semester
            );

            $rows = $mahasiswaItems->map(function (Mahasiswa $mahasiswa) use ($kelasKuliah, $krsByMahasiswa, $repeatHistoryByMahasiswa, $targetMataKuliahId, $candidateSks) {
                $krs = $krsByMahasiswa->get($mahasiswa->id);
                $assessment = $this->assessMahasiswaRegistrationCandidate(
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

    public function registerKrsMahasiswa(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'mahasiswa_ids' => 'required|array|min:1',
            'mahasiswa_ids.*' => 'required|uuid|exists:mahasiswa,id',
        ], [
            'mahasiswa_ids.required' => 'Pilih minimal satu mahasiswa.',
            'mahasiswa_ids.array' => 'Format daftar mahasiswa tidak valid.',
            'mahasiswa_ids.min' => 'Pilih minimal satu mahasiswa.',
            'mahasiswa_ids.*.uuid' => 'ID mahasiswa harus berupa UUID.',
            'mahasiswa_ids.*.exists' => 'Mahasiswa yang dipilih tidak ditemukan.',
        ]);

        try {
            $kelasKuliah = $this->loadKelasForKrsRegistration($id);
            $targetMataKuliahId = $kelasKuliah->kurikulumMataKuliah?->id_mata_kuliah;
            $candidateSks = (int) ($kelasKuliah->kurikulumMataKuliah?->mataKuliah?->sks ?? 0);

            $mahasiswaItems = Mahasiswa::query()
                ->whereIn('id', $validated['mahasiswa_ids'])
                ->get()
                ->keyBy('id');

            $krsByMahasiswa = KRS::query()
                ->with([
                    'details.kelasKuliah.kurikulumMataKuliah',
                    'details.kelasKuliah.jadwal',
                ])
                ->where('id_semester', $kelasKuliah->id_semester)
                ->whereIn('id_mahasiswa', array_keys($mahasiswaItems->all()))
                ->get()
                ->keyBy('id_mahasiswa');

            $results = [];
            $registeredCount = 0;
            $alreadyCount = 0;
            $failedCount = 0;

            foreach ($validated['mahasiswa_ids'] as $mahasiswaId) {
                $mahasiswa = $mahasiswaItems->get($mahasiswaId);

                if (!$mahasiswa) {
                    $results[] = [
                        'id_mahasiswa' => $mahasiswaId,
                        'status' => 'failed',
                        'message' => 'Mahasiswa tidak ditemukan.',
                    ];
                    $failedCount++;
                    continue;
                }

                $krs = $krsByMahasiswa->get($mahasiswa->id);
                $assessment = $this->assessMahasiswaRegistrationCandidate(
                    $mahasiswa,
                    $kelasKuliah,
                    $krs,
                    $targetMataKuliahId,
                    $candidateSks
                );

                if ($assessment['already_registered']) {
                    $results[] = [
                        'id_mahasiswa' => $mahasiswa->id,
                        'nim' => $mahasiswa->nim,
                        'nama_mahasiswa' => $mahasiswa->nama_mahasiswa,
                        'status' => 'skipped',
                        'message' => 'Mahasiswa sudah terdaftar pada kelas ini.',
                    ];
                    $alreadyCount++;
                    continue;
                }

                if (!$assessment['can_register']) {
                    $results[] = [
                        'id_mahasiswa' => $mahasiswa->id,
                        'nim' => $mahasiswa->nim,
                        'nama_mahasiswa' => $mahasiswa->nama_mahasiswa,
                        'status' => 'failed',
                        'message' => $assessment['reason'] ?: 'Mahasiswa belum bisa didaftarkan ke kelas ini.',
                    ];
                    $failedCount++;
                    continue;
                }

                try {
                    $registration = DB::transaction(function () use ($mahasiswa, $kelasKuliah, $krs) {
                        $draftKrs = $krs;

                        if (!$draftKrs) {
                            $draftKrs = KRS::create([
                                'id_mahasiswa' => $mahasiswa->id,
                                'id_semester' => $kelasKuliah->id_semester,
                                'tanggal_pengajuan' => now(),
                                'status_approval' => KRS::STATUS_REVISED,
                                'total_sks' => 0,
                                'is_locked' => false,
                            ]);
                        }

                        $existingDetail = KRSDetail::query()
                            ->where('id_krs', $draftKrs->id)
                            ->where('id_kelas_kuliah', $kelasKuliah->id)
                            ->first();

                        if ($existingDetail) {
                            return ['status' => 'already_registered'];
                        }

                        $kelasKuliah->refresh();
                        if ($kelasKuliah->isPenuh()) {
                            return ['status' => 'class_full'];
                        }

                        KRSDetail::create([
                            'id_krs' => $draftKrs->id,
                            'id_kelas_kuliah' => $kelasKuliah->id,
                            'status' => KRSDetail::STATUS_TERDAFTAR,
                        ]);

                        $draftKrs->update([
                            'total_sks' => $draftKrs->calculateTotalSks(),
                        ]);

                        return [
                            'status' => 'registered',
                            'id_krs' => $draftKrs->id,
                        ];
                    });

                    if (($registration['status'] ?? null) === 'already_registered') {
                        $results[] = [
                            'id_mahasiswa' => $mahasiswa->id,
                            'nim' => $mahasiswa->nim,
                            'nama_mahasiswa' => $mahasiswa->nama_mahasiswa,
                            'status' => 'skipped',
                            'message' => 'Mahasiswa sudah terdaftar pada kelas ini.',
                        ];
                        $alreadyCount++;
                        continue;
                    }

                    if (($registration['status'] ?? null) === 'class_full') {
                        $results[] = [
                            'id_mahasiswa' => $mahasiswa->id,
                            'nim' => $mahasiswa->nim,
                            'nama_mahasiswa' => $mahasiswa->nama_mahasiswa,
                            'status' => 'failed',
                            'message' => 'Kelas sudah penuh saat proses pendaftaran dijalankan.',
                        ];
                        $failedCount++;
                        continue;
                    }

                    $results[] = [
                        'id_mahasiswa' => $mahasiswa->id,
                        'nim' => $mahasiswa->nim,
                        'nama_mahasiswa' => $mahasiswa->nama_mahasiswa,
                        'status' => 'registered',
                        'message' => 'Mahasiswa berhasil didaftarkan ke kelas ini.',
                    ];
                    $registeredCount++;
                } catch (Exception $e) {
                    $results[] = [
                        'id_mahasiswa' => $mahasiswa->id,
                        'nim' => $mahasiswa->nim,
                        'nama_mahasiswa' => $mahasiswa->nama_mahasiswa,
                        'status' => 'failed',
                        'message' => 'Gagal mendaftarkan mahasiswa: ' . $e->getMessage(),
                    ];
                    $failedCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Proses pendaftaran KRS selesai.',
                'data' => [
                    'registered_count' => $registeredCount,
                    'already_registered_count' => $alreadyCount,
                    'failed_count' => $failedCount,
                    'results' => $results,
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mendaftarkan mahasiswa ke KRS.',
                'error' => $e->getMessage(),
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
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $kelasKuliah = KelasKuliah::findOrFail($id);

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
        $kelasKuliah = KelasKuliah::findOrFail($id);

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

    private function loadKelasForKrsRegistration(string $id): KelasKuliah
    {
        return KelasKuliah::query()
            ->with([
                'kurikulumMataKuliah.mataKuliah.prasyarat.mataKuliahPrasyarat',
                'jadwal',
            ])
            ->findOrFail($id);
    }

    private function assessMahasiswaRegistrationCandidate(
        Mahasiswa $mahasiswa,
        KelasKuliah $kelasKuliah,
        ?KRS $krs,
        ?string $targetMataKuliahId,
        int $candidateSks
    ): array {
        $details = collect($krs?->details ?? []);
        $existingDetail = $details->firstWhere('id_kelas_kuliah', $kelasKuliah->id);

        if ($existingDetail) {
            return [
                'already_registered' => true,
                'can_register' => false,
                'state' => 'registered',
                'state_label' => 'Sudah terdaftar',
                'state_variant' => 'success',
                'reason' => 'Mahasiswa sudah terdaftar pada kelas ini.',
            ];
        }

        if (strtolower((string) $mahasiswa->status) !== 'aktif') {
            return [
                'already_registered' => false,
                'can_register' => false,
                'state' => 'inactive',
                'state_label' => 'Mahasiswa tidak aktif',
                'state_variant' => 'secondary',
                'reason' => 'Mahasiswa berstatus ' . ($mahasiswa->status ?? 'tidak aktif') . '.',
            ];
        }

        if ($krs && !$krs->isEditable()) {
            return [
                'already_registered' => false,
                'can_register' => false,
                'state' => 'locked',
                'state_label' => 'KRS tidak bisa diubah',
                'state_variant' => 'warning',
                'reason' => 'Draft KRS mahasiswa pada semester ini tidak dapat diubah.',
            ];
        }

        if ($kelasKuliah->isPenuh()) {
            return [
                'already_registered' => false,
                'can_register' => false,
                'state' => 'class_full',
                'state_label' => 'Kelas penuh',
                'state_variant' => 'danger',
                'reason' => 'Kapasitas kelas sudah penuh.',
            ];
        }

        $prerequisiteCheck = $this->validatePrerequisites(
            $mahasiswa->id,
            $kelasKuliah->kurikulumMataKuliah?->mataKuliah
        );

        if (!$prerequisiteCheck['passed']) {
            return [
                'already_registered' => false,
                'can_register' => false,
                'state' => 'prerequisite',
                'state_label' => 'Prasyarat belum terpenuhi',
                'state_variant' => 'warning',
                'reason' => $prerequisiteCheck['message'],
            ];
        }

        if ($targetMataKuliahId && $this->hasDuplicateCourseSelection($details, $targetMataKuliahId)) {
            return [
                'already_registered' => false,
                'can_register' => false,
                'state' => 'duplicate_course',
                'state_label' => 'Matakuliah sudah diambil',
                'state_variant' => 'warning',
                'reason' => 'Matakuliah ini sudah terdaftar pada kelas lain di KRS mahasiswa.',
            ];
        }

        $currentSks = (int) ($krs?->total_sks ?? 0);
        if (($currentSks + $candidateSks) > 24) {
            return [
                'already_registered' => false,
                'can_register' => false,
                'state' => 'sks_limit',
                'state_label' => 'Melebihi batas SKS',
                'state_variant' => 'warning',
                'reason' => 'Penambahan kelas ini akan melebihi batas maksimal 24 SKS.',
            ];
        }

        if ($this->hasScheduleConflict($details, $kelasKuliah)) {
            return [
                'already_registered' => false,
                'can_register' => false,
                'state' => 'schedule_conflict',
                'state_label' => 'Jadwal bentrok',
                'state_variant' => 'danger',
                'reason' => 'Jadwal kelas ini bertabrakan dengan kelas lain di KRS mahasiswa.',
            ];
        }

        return [
            'already_registered' => false,
            'can_register' => true,
            'state' => 'available',
            'state_label' => 'Siap didaftarkan',
            'state_variant' => 'primary',
            'reason' => null,
        ];
    }

    private function validatePrerequisites(string $mahasiswaId, $mataKuliah): array
    {
        if (!$mataKuliah) {
            return [
                'passed' => false,
                'message' => 'Data mata kuliah tidak ditemukan.',
                'requirements' => [],
            ];
        }

        $requirements = [];

        foreach ($mataKuliah->prasyarat ?? [] as $prasyarat) {
            $mkPrasyarat = $prasyarat->mataKuliahPrasyarat;

            if (!$mkPrasyarat) {
                continue;
            }

            $equivalentCourseIds = $this->curriculumConversionService
                ->getRecognizedSourceCourseIdsForTarget($mahasiswaId, $mkPrasyarat->id);

            $hasPassed = KRSDetail::query()
                ->whereHas('krs', function ($query) use ($mahasiswaId) {
                    $query->where('id_mahasiswa', $mahasiswaId)
                        ->where('status_approval', KRS::STATUS_APPROVED);
                })
                ->whereHas('kelasKuliah.kurikulumMataKuliah.mataKuliah', function ($query) use ($equivalentCourseIds) {
                    $query->whereIn('mata_kuliah.id', $equivalentCourseIds);
                })
                ->where('status', KRSDetail::STATUS_LULUS)
                ->where('bobot_nilai', '>=', $prasyarat->min_bobot_nilai)
                ->exists();

            $requirements[] = [
                'kode_mk' => $mkPrasyarat->kode_mk,
                'nama_mk' => $mkPrasyarat->nama_mk,
                'min_bobot_nilai' => $prasyarat->min_bobot_nilai,
                'is_passed' => $hasPassed,
            ];
        }

        $missing = array_values(array_filter($requirements, fn($item) => !$item['is_passed']));

        if ($missing !== []) {
            $first = $missing[0];

            return [
                'passed' => false,
                'message' => "Prasyarat {$first['kode_mk']} - {$first['nama_mk']} belum terpenuhi",
                'requirements' => $requirements,
            ];
        }

        return [
            'passed' => true,
            'message' => null,
            'requirements' => $requirements,
        ];
    }

    private function resolveRepeatHistoryByMahasiswa(array $mahasiswaIds, ?string $targetMataKuliahId, string $currentSemesterId): Collection
    {
        if ($mahasiswaIds === [] || !filled($targetMataKuliahId)) {
            return collect();
        }

        return KRSDetail::query()
            ->with([
                'krs.semester.tahunAkademik',
                'kelasKuliah.kurikulumMataKuliah.mataKuliah',
            ])
            ->whereIn('status', [KRSDetail::STATUS_LULUS, KRSDetail::STATUS_TIDAK_LULUS])
            ->whereHas('krs', function ($query) use ($mahasiswaIds, $currentSemesterId) {
                $query->whereIn('id_mahasiswa', $mahasiswaIds)
                    ->where('status_approval', KRS::STATUS_APPROVED)
                    ->where('id_semester', '!=', $currentSemesterId);
            })
            ->whereHas('kelasKuliah.kurikulumMataKuliah', function ($query) use ($targetMataKuliahId) {
                $query->where('id_mata_kuliah', $targetMataKuliahId);
            })
            ->get()
            ->groupBy(fn(KRSDetail $detail) => $detail->krs?->id_mahasiswa)
            ->map(function (Collection $items) {
                $latest = $items->sortByDesc(function (KRSDetail $detail) {
                    return optional($detail->krs)->tanggal_pengajuan ?? $detail->created_at;
                })->first();

                if (!$latest || $latest->status !== KRSDetail::STATUS_TIDAK_LULUS) {
                    return null;
                }

                return [
                    'status' => $latest->status,
                    'nilai_huruf' => $latest->nilai_huruf,
                    'nilai_akhir' => $latest->nilai_akhir,
                    'bobot_nilai' => $latest->bobot_nilai,
                    'semester' => $latest->krs?->semester?->tahunAkademik?->tahun_akademik
                        ? trim(($latest->krs->semester->tahunAkademik->tahun_akademik ?? '') . ' ' . ($latest->krs->semester->nama_semester ?? ''))
                        : null,
                ];
            })
            ->filter();
    }

    private function hasDuplicateCourseSelection(Collection $details, string $targetMataKuliahId): bool
    {
        return $details->contains(function (KRSDetail $detail) use ($targetMataKuliahId) {
            return (string) ($detail->kelasKuliah?->kurikulumMataKuliah?->id_mata_kuliah ?? '') === $targetMataKuliahId;
        });
    }

    private function hasScheduleConflict(Collection $details, KelasKuliah $kelasKuliah): bool
    {
        foreach ($details as $detail) {
            $existingClass = $detail->kelasKuliah;

            if (!$existingClass) {
                continue;
            }

            foreach ($kelasKuliah->jadwal as $candidateSchedule) {
                foreach ($existingClass->jadwal as $existingSchedule) {
                    if (
                        $candidateSchedule->hari === $existingSchedule->hari
                        && $this->isTimeOverlap(
                            $candidateSchedule->jam_mulai,
                            $candidateSchedule->jam_selesai,
                            $existingSchedule->jam_mulai,
                            $existingSchedule->jam_selesai
                        )
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function isTimeOverlap(string $start1, string $end1, string $start2, string $end2): bool
    {
        return ($start1 < $end2) && ($start2 < $end1);
    }
}
