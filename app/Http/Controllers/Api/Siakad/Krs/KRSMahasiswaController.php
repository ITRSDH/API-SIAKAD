<?php

namespace App\Http\Controllers\Api\Siakad\Krs;

use App\Http\Controllers\Controller;
use App\Models\Akademik\KRS;
use App\Models\Akademik\KRSDetail;
use App\Models\MasterData\KelasKuliah;
use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\PeriodeKrs;
use App\Models\MasterData\Semester;
use App\Services\ActiveCurriculumService;
use App\Services\CurriculumConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class KRSMahasiswaController extends Controller
{
    public function __construct(
        private readonly CurriculumConversionService $curriculumConversionService,
        private readonly ActiveCurriculumService $activeCurriculumService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $mahasiswa = $this->getAuthenticatedMahasiswa($request);

        if (!$mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Data mahasiswa tidak ditemukan'
            ], 404);
        }

        $krsList = KRS::with($this->krsRelations())
            ->where('id_mahasiswa', $mahasiswa->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn(KRS $krs) => $this->transformKRS($krs));

        return response()->json([
            'success' => true,
            'data' => $krsList,
        ]);
    }

    public function current(Request $request): JsonResponse
    {
        $mahasiswa = $this->getAuthenticatedMahasiswa($request);

        if (!$mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Data mahasiswa tidak ditemukan'
            ], 404);
        }

        $semester = $this->getActiveSemester();

        if (!$semester) {
            return response()->json([
                'success' => false,
                'message' => 'Semester aktif tidak ditemukan'
            ], 404);
        }

        $semesterSaatIni = $this->hitungSemesterBerjalan($mahasiswa);

        $krs = KRS::with($this->krsRelations())
            ->where('id_mahasiswa', $mahasiswa->id)
            ->where('id_semester', $semester->id)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'semester_aktif' => $semester,
                'semester_saat_ini' => $semesterSaatIni,
                'mahasiswa' => $mahasiswa,
                'krs' => $krs ? $this->transformKRS($krs) : null,
                'has_krs' => (bool) $krs,
            ],
        ]);
    }

    public function initCurrent(Request $request): JsonResponse
    {
        $mahasiswa = $this->getAuthenticatedMahasiswa($request);

        if (!$mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Data mahasiswa tidak ditemukan'
            ], 404);
        }

        $semester = $this->getActiveSemester();

        if (!$semester) {
            return response()->json([
                'success' => false,
                'message' => 'Semester aktif tidak ditemukan'
            ], 404);
        }

        $periodError = $this->validateKRSPeriod($semester);
        if ($periodError) {
            return $periodError;
        }

        $krs = KRS::with($this->krsRelations())
            ->where('id_mahasiswa', $mahasiswa->id)
            ->where('id_semester', $semester->id)
            ->first();

        if (!$krs) {
            $result = DB::transaction(function () use ($mahasiswa, $semester) {
                $krs = KRS::create([
                    'id_mahasiswa' => $mahasiswa->id,
                    'id_semester' => $semester->id,
                    'tanggal_pengajuan' => now(),
                    'status_approval' => KRS::STATUS_REVISED,
                    'total_sks' => 0,
                    'is_locked' => false,
                ]);

                $packageResult = $this->generatePackageKrs($krs, $mahasiswa, $semester);

                return [
                    'krs' => $krs,
                    'package_result' => $packageResult,
                ];
            });

            $krs = $result['krs'];
            $packageResult = $result['package_result'];
            $krs->load($this->krsRelations());

            return response()->json([
                'success' => true,
                'message' => 'Draft KRS semester aktif berhasil dibuat dan paket semester telah digenerate',
                'data' => [
                    'krs' => $this->transformKRS($krs),
                    'package_summary' => $packageResult['summary'],
                    'unresolved_package_items' => $packageResult['unresolved_items'],
                ],
            ], 201);
        }

        return response()->json([
            'success' => true,
            'message' => 'Draft KRS semester aktif sudah tersedia',
            'data' => $this->transformKRS($krs),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $mahasiswa = $this->getAuthenticatedMahasiswa($request);

        if (!$mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Data mahasiswa tidak ditemukan'
            ], 404);
        }

        $krs = KRS::with($this->krsRelations())
            ->where('id', $id)
            ->where('id_mahasiswa', $mahasiswa->id)
            ->first();

        if (!$krs) {
            return response()->json([
                'success' => false,
                'message' => 'KRS tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->transformKRS($krs),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_semester' => 'required|uuid|exists:semester,id',
        ], [
            'id_semester.required' => 'ID semester wajib diisi',
            'id_semester.uuid' => 'ID semester harus berupa UUID',
            'id_semester.exists' => 'Semester tidak ditemukan',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $mahasiswa = $this->getAuthenticatedMahasiswa($request);

        if (!$mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Data mahasiswa tidak ditemukan'
            ], 404);
        }

        $semester = Semester::find($request->id_semester);
        $periodError = $this->validateKRSPeriod($semester);
        if ($periodError) {
            return $periodError;
        }

        $existingKRS = KRS::with($this->krsRelations())
            ->where('id_mahasiswa', $mahasiswa->id)
            ->where('id_semester', $request->id_semester)
            ->first();

        if ($existingKRS) {
            return response()->json([
                'success' => true,
                'message' => 'KRS untuk semester ini sudah ada',
                'data' => $this->transformKRS($existingKRS),
            ]);
        }

        try {
            $result = DB::transaction(function () use ($mahasiswa, $request, $semester) {
                $krs = KRS::create([
                    'id_mahasiswa' => $mahasiswa->id,
                    'id_semester' => $request->id_semester,
                    'tanggal_pengajuan' => now(),
                    'status_approval' => KRS::STATUS_REVISED,
                    'total_sks' => 0,
                    'is_locked' => false,
                ]);

                $packageResult = $this->generatePackageKrs($krs, $mahasiswa, $semester);

                return [
                    'krs' => $krs,
                    'package_result' => $packageResult,
                ];
            });

            $krs = $result['krs'];
            $packageResult = $result['package_result'];
            $krs->load($this->krsRelations());

            return response()->json([
                'success' => true,
                'message' => 'Draft KRS berhasil dibuat dan paket semester telah digenerate',
                'data' => [
                    'krs' => $this->transformKRS($krs),
                    'package_summary' => $packageResult['summary'],
                    'unresolved_package_items' => $packageResult['unresolved_items'],
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat KRS: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAvailableMataKuliah(Request $request): JsonResponse
    {
        $mahasiswa = $this->getAuthenticatedMahasiswa($request);

        if (!$mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Data mahasiswa tidak ditemukan'
            ], 404);
        }

        $semester = null;
        $krs = null;

        if ($request->filled('id_krs')) {
            $krs = KRS::where('id', $request->id_krs)
                ->where('id_mahasiswa', $mahasiswa->id)
                ->first();

            if (!$krs) {
                return response()->json([
                    'success' => false,
                    'message' => 'KRS tidak ditemukan'
                ], 404);
            }

            $semester = Semester::find($krs->id_semester);
        } elseif ($request->filled('id_semester')) {
            $semester = Semester::find($request->id_semester);
        } else {
            $semester = $this->getActiveSemester();
            if ($semester) {
                $krs = KRS::where('id_mahasiswa', $mahasiswa->id)
                    ->where('id_semester', $semester->id)
                    ->first();
            }
        }

        if (!$semester) {
            return response()->json([
                'success' => false,
                'message' => 'Semester tidak ditemukan'
            ], 404);
        }

        $selectedKelasIds = [];
        if ($krs) {
            $selectedKelasIds = KRSDetail::where('id_krs', $krs->id)
                ->pluck('id_kelas_kuliah')
                ->toArray();
        }

        $availableKelas = KelasKuliah::where('id_prodi', $mahasiswa->id_prodi)
            ->where('id_semester', $semester->id)
            ->with([
                'kurikulumMataKuliah.mataKuliah.prasyarat.mataKuliahPrasyarat',
                'jadwal',
                'dosen_pengajar.dosen'
            ])
            ->get();

        $mahasiswaSemester = $this->hitungSemesterBerjalan($mahasiswa);
        $currentSks = $krs ? $krs->calculateTotalSks() : 0;
        $maxSks = $this->getMaxSksAllowed($mahasiswa);

        $result = [];

        foreach ($availableKelas as $kelas) {
            $mataKuliah = $kelas->kurikulumMataKuliah->mataKuliah;
            $isSelected = in_array($kelas->id, $selectedKelasIds, true);
            $semesterAllowed = $mahasiswaSemester >= $kelas->kurikulumMataKuliah->semester_ke;
            $wouldExceedSks = ($currentSks + $mataKuliah->sks) > $maxSks;
            $hasConflict = $krs ? $this->hasJadwalKonflik($krs, $kelas) : false;
            $kelasPenuh = $kelas->isPenuh();
            $prasyaratCheck = $this->validatePrerequisites($mahasiswa->id, $mataKuliah);

            $availabilityReason = null;
            if ($isSelected) {
                $availabilityReason = 'Mata kuliah sudah ada di KRS';
            } elseif (!$semesterAllowed) {
                $availabilityReason = 'Mata kuliah belum sesuai semester tempuh mahasiswa';
            } elseif (!$prasyaratCheck['passed']) {
                $availabilityReason = $prasyaratCheck['message'];
            } elseif ($wouldExceedSks) {
                $availabilityReason = "Penambahan kelas ini akan melebihi batas maksimal {$maxSks} SKS";
            } elseif ($hasConflict) {
                $availabilityReason = 'Jadwal bertabrakan dengan mata kuliah lain';
            } elseif ($kelasPenuh) {
                $availabilityReason = 'Kelas sudah penuh';
            }

            $result[] = [
                'id' => $kelas->id,
                'nama_kelas' => $kelas->nama_kelas,
                'mata_kuliah' => $mataKuliah->nama_mk,
                'kode_mk' => $mataKuliah->kode_mk,
                'sks' => $mataKuliah->sks,
                'semester_ke' => $kelas->kurikulumMataKuliah->semester_ke,
                'is_wajib' => $kelas->kurikulumMataKuliah->is_wajib,
                'kapasitas_peserta' => $kelas->kapasitas_peserta,
                'peserta_terdaftar' => $kelas->peserta_terdaftar_count,
                'jadwal' => $kelas->jadwal,
                'dosen_pengajar' => $kelas->dosen_pengajar,
                'is_selected' => $isSelected,
                'is_available' => $availabilityReason === null,
                'availability_reason' => $availabilityReason,
                'prasyarat' => $prasyaratCheck['requirements'],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'meta' => [
                'id_semester' => $semester->id,
                'id_krs' => $krs?->id,
                'current_sks' => $currentSks,
                'max_sks_allowed' => $maxSks,
            ],
        ]);
    }

    public function repeatCandidates(Request $request): JsonResponse
    {
        $mahasiswa = $this->getAuthenticatedMahasiswa($request);

        if (!$mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Data mahasiswa tidak ditemukan'
            ], 404);
        }

        $semester = null;
        $krs = null;

        if ($request->filled('id_krs')) {
            $krs = KRS::where('id', $request->id_krs)
                ->where('id_mahasiswa', $mahasiswa->id)
                ->first();

            if (!$krs) {
                return response()->json([
                    'success' => false,
                    'message' => 'KRS tidak ditemukan'
                ], 404);
            }

            $semester = Semester::find($krs->id_semester);
        } else {
            $semester = $this->getActiveSemester();
            if ($semester) {
                $krs = KRS::where('id_mahasiswa', $mahasiswa->id)
                    ->where('id_semester', $semester->id)
                    ->first();
            }
        }

        if (!$semester || !$krs) {
            return response()->json([
                'success' => false,
                'message' => 'Draft KRS semester aktif belum tersedia'
            ], 404);
        }

        $currentSks = $krs->calculateTotalSks();
        $maxSks = $this->getMaxSksAllowed($mahasiswa);
        $selectedMataKuliahIds = $this->getSelectedMataKuliahIds($krs);
        $activeKurikulumId = $this->activeCurriculumService->resolveActiveKurikulumId($mahasiswa);

        $failedHistories = KRSDetail::query()
            ->whereHas('krs', function ($query) use ($mahasiswa, $semester) {
                $query->where('id_mahasiswa', $mahasiswa->id)
                    ->where('status_approval', KRS::STATUS_APPROVED)
                    ->where('id_semester', '!=', $semester->id);
            })
            ->where('status', KRSDetail::STATUS_TIDAK_LULUS)
            ->with([
                'mataKuliah',
                'kelasKuliah.kurikulumMataKuliah.mataKuliah',
                'krs.semester.tahunAkademik',
            ])
            ->get()
            ->groupBy(function (KRSDetail $detail) use ($mahasiswa, $activeKurikulumId) {
                $sourceCourseId = $detail->id_mata_kuliah
                    ?? $detail->mataKuliah?->id
                    ?? $detail->kelasKuliah?->kurikulumMataKuliah?->mataKuliah?->id;
                if (!filled($sourceCourseId)) {
                    return null;
                }

                return $this->curriculumConversionService
                    ->resolveTranscriptCourse($mahasiswa->id, $sourceCourseId, $activeKurikulumId)?->id
                    ?? $sourceCourseId;
            })
            ->map(function (Collection $items) use ($semester) {
                return $items->filter(function (KRSDetail $detail) use ($semester) {
                    return $this->isSemesterBefore(
                        $detail->krs?->semester,
                        $semester
                    );
                });
            })
            ->filter(fn(Collection $items) => $items->isNotEmpty())
            ->filter(fn($items, $mataKuliahId) => filled($mataKuliahId));

        $result = [];

        foreach ($failedHistories as $mataKuliahId => $histories) {
            if (in_array($mataKuliahId, $selectedMataKuliahIds, true)) {
                continue;
            }

            $latestHistory = $histories->sortByDesc(function (KRSDetail $detail) {
                return optional($detail->krs)->tanggal_pengajuan ?? $detail->created_at;
            })->first();

            $sourceCourseId = $latestHistory?->id_mata_kuliah
                ?? $latestHistory?->mataKuliah?->id
                ?? $latestHistory?->kelasKuliah?->kurikulumMataKuliah?->mataKuliah?->id;
            $mataKuliah = filled($sourceCourseId)
                ? $this->curriculumConversionService->resolveTranscriptCourse($mahasiswa->id, $sourceCourseId, $activeKurikulumId)
                : null;

            if (!$mataKuliah) {
                continue;
            }

            $kelasTersedia = KelasKuliah::query()
                ->where('id_semester', $semester->id)
                ->where('id_prodi', $mahasiswa->id_prodi)
                ->whereHas('kurikulumMataKuliah', function ($query) use ($mataKuliahId) {
                    $query->where('id_mata_kuliah', $mataKuliahId);
                })
                ->with([
                    'kurikulumMataKuliah.mataKuliah.prasyarat.mataKuliahPrasyarat',
                    'jadwal',
                    'dosen_pengajar.dosen',
                ])
                ->orderBy('nama_kelas')
                ->get()
                ->map(function (KelasKuliah $kelas) use ($mahasiswa, $krs, $currentSks, $maxSks) {
                    $mataKuliah = $kelas->kurikulumMataKuliah->mataKuliah;
                    $prasyaratCheck = $this->validatePrerequisites($mahasiswa->id, $mataKuliah);
                    $wouldExceedSks = ($currentSks + ($mataKuliah->sks ?? 0)) > $maxSks;
                    $hasConflict = $this->hasJadwalKonflik($krs, $kelas);
                    $kelasPenuh = $kelas->isPenuh();

                    $availabilityReason = null;
                    if (!$prasyaratCheck['passed']) {
                        $availabilityReason = $prasyaratCheck['message'];
                    } elseif ($wouldExceedSks) {
                        $availabilityReason = "Penambahan kelas ini akan melebihi batas maksimal {$maxSks} SKS";
                    } elseif ($hasConflict) {
                        $availabilityReason = 'Jadwal bertabrakan dengan mata kuliah lain';
                    } elseif ($kelasPenuh) {
                        $availabilityReason = 'Kelas sudah penuh';
                    }

                    return [
                        'id_kelas_kuliah' => $kelas->id,
                        'nama_kelas' => $kelas->nama_kelas,
                        'sks' => $mataKuliah->sks,
                        'semester_ke' => $kelas->kurikulumMataKuliah->semester_ke,
                        'jadwal' => $kelas->jadwal,
                        'dosen_pengajar' => $kelas->dosen_pengajar,
                        'is_available' => $availabilityReason === null,
                        'availability_reason' => $availabilityReason,
                    ];
                })
                ->values();

            $result[] = [
                'id_mata_kuliah' => $mataKuliah->id,
                'kode_mk' => $mataKuliah->kode_mk,
                'nama_mk' => $mataKuliah->nama_mk,
                'sks' => $mataKuliah->sks,
                'riwayat_terakhir' => [
                    'semester' => $latestHistory?->krs?->semester,
                    'status' => $latestHistory?->status,
                    'nilai_huruf' => $latestHistory?->nilai_huruf,
                    'bobot_nilai' => $latestHistory?->bobot_nilai,
                    'nilai_akhir' => $latestHistory?->nilai_akhir,
                ],
                'kelas_tersedia' => $kelasTersedia,
                'availability_reason' => $kelasTersedia->isEmpty()
                    ? 'Belum ada kelas aktif untuk mata kuliah ini pada semester berjalan.'
                    : null,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => array_values($result),
            'meta' => [
                'id_krs' => $krs->id,
                'current_sks' => $currentSks,
                'max_sks_allowed' => $maxSks,
                'remaining_sks' => max($maxSks - $currentSks, 0),
            ],
        ]);
    }

    public function addMataKuliah(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_krs' => 'required|uuid|exists:krs,id',
            'id_kelas_kuliah' => 'required|uuid|exists:kelas_kuliah,id',
            'force_override' => 'nullable|boolean',
            'override_note' => 'nullable|string|max:1000',
        ], [
            'id_krs.required' => 'ID KRS wajib diisi',
            'id_krs.uuid' => 'ID KRS harus berupa UUID',
            'id_krs.exists' => 'KRS tidak ditemukan',
            'id_kelas_kuliah.required' => 'ID kelas kuliah wajib diisi',
            'id_kelas_kuliah.uuid' => 'ID kelas kuliah harus berupa UUID',
            'id_kelas_kuliah.exists' => 'Kelas kuliah tidak ditemukan',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $mahasiswa = $this->getAuthenticatedMahasiswa($request);

        if (!$mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Data mahasiswa tidak ditemukan'
            ], 404);
        }

        $krs = KRS::with(['mahasiswa'])
            ->where('id', $request->id_krs)
            ->where('id_mahasiswa', $mahasiswa->id)
            ->first();

        if (!$krs) {
            return response()->json([
                'success' => false,
                'message' => 'KRS tidak ditemukan'
            ], 404);
        }

        if (!$krs->isEditable()) {
            return response()->json([
                'success' => false,
                'message' => 'KRS tidak dapat diubah pada status saat ini'
            ], 400);
        }

        $kelasKuliah = KelasKuliah::with([
            'kurikulumMataKuliah.mataKuliah.prasyarat.mataKuliahPrasyarat',
            'jadwal'
        ])->findOrFail($request->id_kelas_kuliah);

        if ($krs->mahasiswa->id_prodi !== $kelasKuliah->id_prodi) {
            return response()->json([
                'success' => false,
                'message' => 'Mata kuliah tidak sesuai dengan program studi mahasiswa'
            ], 400);
        }

        if ($krs->id_semester !== $kelasKuliah->id_semester) {
            return response()->json([
                'success' => false,
                'message' => 'Mata kuliah tidak ditawarkan pada semester ini'
            ], 400);
        }

        $existingDetail = KRSDetail::where('id_krs', $request->id_krs)
            ->where('id_kelas_kuliah', $request->id_kelas_kuliah)
            ->first();

        if ($existingDetail) {
            return response()->json([
                'success' => false,
                'message' => 'Mata kuliah sudah ada di KRS'
            ], 400);
        }

        $targetMataKuliahId = $kelasKuliah->kurikulumMataKuliah->id_mata_kuliah ?? null;
        if ($targetMataKuliahId && in_array($targetMataKuliahId, $this->getSelectedMataKuliahIds($krs), true)) {
            return response()->json([
                'success' => false,
                'message' => 'Mata kuliah ini sudah terdaftar di KRS pada kelas lain'
            ], 400);
        }

        $currentSks = $krs->calculateTotalSks();
        $mataKuliahSks = $kelasKuliah->kurikulumMataKuliah->mataKuliah->sks;
        $maxSks = $this->getMaxSksAllowed($mahasiswa);
        $prasyaratCheck = $this->validatePrerequisites($mahasiswa->id, $kelasKuliah->kurikulumMataKuliah->mataKuliah);
        $isOverrideRequested = $request->boolean('force_override');
        $canForceOverride = $this->canForceSksOverride($request);

        if (!$prasyaratCheck['passed']) {
            return response()->json([
                'success' => false,
                'message' => $prasyaratCheck['message'],
                'data' => [
                    'requirements' => $prasyaratCheck['requirements'],
                ],
            ], 400);
        }

        if ($currentSks + $mataKuliahSks > $maxSks) {
            if (!$isOverrideRequested || !$canForceOverride) {
                $message = $canForceOverride
                    ? "Total SKS melebihi batas maksimal ({$maxSks} SKS). Gunakan override jika ini memang disetujui kampus."
                    : "Total SKS melebihi batas maksimal ({$maxSks} SKS)";

                return response()->json([
                    'success' => false,
                    'message' => $message
                ], 400);
            }

            if (!filled($request->override_note)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Catatan override wajib diisi saat melampaui batas maksimal SKS'
                ], 422);
            }
        }

        if ($isOverrideRequested && !$canForceOverride) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak untuk melakukan override batas SKS'
            ], 403);
        }

        if ($kelasKuliah->isPenuh()) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas sudah penuh'
            ], 400);
        }

        if ($this->hasJadwalKonflik($krs, $kelasKuliah)) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal bertabrakan dengan mata kuliah lain'
            ], 400);
        }

        try {
            $detail = DB::transaction(function () use ($request, $krs, $currentSks, $mataKuliahSks, $maxSks, $isOverrideRequested) {
                $detail = KRSDetail::create([
                    'id_krs' => $request->id_krs,
                    'id_kelas_kuliah' => $request->id_kelas_kuliah,
                    'status' => KRSDetail::STATUS_TERDAFTAR,
                ]);

                $newTotalSks = $krs->calculateTotalSks();

                $payload = ['total_sks' => $newTotalSks];
                if (($currentSks + $mataKuliahSks > $maxSks) && $isOverrideRequested) {
                    $payload = array_merge($payload, [
                        'is_sks_override' => true,
                        'sks_override_reason' => $request->override_note,
                        'sks_override_by' => optional($request->user())->id,
                        'sks_override_at' => now(),
                    ]);
                }

                $krs->update($payload);

                return $detail;
            });

            $updatedKrs = $this->loadKRSForResponse($krs->id);

            return response()->json([
                'success' => true,
                'message' => 'Mata kuliah berhasil ditambahkan',
                'data' => [
                    'detail' => $detail->load(['kelasKuliah.kurikulumMataKuliah.mataKuliah']),
                    'krs' => $this->transformKRS($updatedKrs),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan mata kuliah: ' . $e->getMessage()
            ], 500);
        }
    }

    public function removeMataKuliah(Request $request, string $krsId, string $kelasKuliahId): JsonResponse
    {
        $mahasiswa = $this->getAuthenticatedMahasiswa($request);

        if (!$mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Data mahasiswa tidak ditemukan'
            ], 404);
        }

        $krs = KRS::where('id', $krsId)
            ->where('id_mahasiswa', $mahasiswa->id)
            ->first();

        if (!$krs) {
            return response()->json([
                'success' => false,
                'message' => 'KRS tidak ditemukan'
            ], 404);
        }

        if (!$krs->isEditable()) {
            return response()->json([
                'success' => false,
                'message' => 'KRS tidak dapat diubah pada status saat ini'
            ], 400);
        }

        $detail = KRSDetail::where('id_krs', $krsId)
            ->where('id_kelas_kuliah', $kelasKuliahId)
            ->first();

        if (!$detail) {
            return response()->json([
                'success' => false,
                'message' => 'Mata kuliah tidak ditemukan di KRS'
            ], 404);
        }

        try {
            DB::transaction(function () use ($detail, $krs) {
                $detail->delete();
                $newTotalSks = $krs->calculateTotalSks();
                $krs->update(['total_sks' => $newTotalSks]);

                if ($newTotalSks <= $this->getMaxSksAllowed($krs->mahasiswa)) {
                    $krs->clearSksOverride();
                }
            });

            $updatedKrs = $this->loadKRSForResponse($krs->id);

            return response()->json([
                'success' => true,
                'message' => 'Mata kuliah berhasil dihapus',
                'data' => $this->transformKRS($updatedKrs),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus mata kuliah: ' . $e->getMessage()
            ], 500);
        }
    }

    public function submit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_krs' => 'required|uuid|exists:krs,id',
        ], [
            'id_krs.required' => 'ID KRS wajib diisi',
            'id_krs.uuid' => 'ID KRS harus berupa UUID',
            'id_krs.exists' => 'KRS tidak ditemukan',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $mahasiswa = $this->getAuthenticatedMahasiswa($request);

        if (!$mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Data mahasiswa tidak ditemukan'
            ], 404);
        }

        $krs = $this->loadKRSForResponse($request->id_krs);

        if (!$krs || $krs->id_mahasiswa !== $mahasiswa->id) {
            return response()->json([
                'success' => false,
                'message' => 'KRS tidak ditemukan'
            ], 404);
        }

        if (!$krs->isEditable()) {
            return response()->json([
                'success' => false,
                'message' => 'KRS tidak dapat diajukan pada status saat ini'
            ], 400);
        }

        $periodError = $this->validateKRSPeriod($krs->semester);
        if ($periodError) {
            return $periodError;
        }

        $validationSummary = $this->buildValidationSummary($krs);
        if (!$validationSummary['is_valid']) {
            return response()->json([
                'success' => false,
                'message' => 'KRS belum dapat diajukan karena masih ada validasi yang belum terpenuhi',
                'data' => $validationSummary,
            ], 400);
        }

        try {
            $krs->update([
                'status_approval' => KRS::STATUS_PENDING,
                'tanggal_pengajuan' => now(),
                'catatan' => null,
            ]);

            $krs->refresh()->load($this->krsRelations());

            return response()->json([
                'success' => true,
                'message' => 'KRS berhasil diajukan ke dosen wali',
                'data' => $this->transformKRS($krs),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengajukan KRS: ' . $e->getMessage()
            ], 500);
        }
    }

    public function validationSummary(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_krs' => 'required|uuid|exists:krs,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $mahasiswa = $this->getAuthenticatedMahasiswa($request);

        if (!$mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Data mahasiswa tidak ditemukan'
            ], 404);
        }

        $krs = $this->loadKRSForResponse($request->id_krs);

        if (!$krs || $krs->id_mahasiswa !== $mahasiswa->id) {
            return response()->json([
                'success' => false,
                'message' => 'KRS tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->buildValidationSummary($krs),
        ]);
    }

    public function statistics(Request $request): JsonResponse
    {
        $mahasiswa = $this->getAuthenticatedMahasiswa($request);

        if (!$mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Data mahasiswa tidak ditemukan'
            ], 404);
        }

        $activeSemester = $this->getActiveSemester();
        $currentSemesterKrs = $activeSemester
            ? KRS::where('id_mahasiswa', $mahasiswa->id)->where('id_semester', $activeSemester->id)->first()
            : null;

        $stats = [
            'total_krs' => KRS::where('id_mahasiswa', $mahasiswa->id)->count(),
            'draft_or_revision_krs' => KRS::where('id_mahasiswa', $mahasiswa->id)->revised()->count(),
            'pending_krs' => KRS::where('id_mahasiswa', $mahasiswa->id)->pending()->count(),
            'approved_krs' => KRS::where('id_mahasiswa', $mahasiswa->id)->approved()->count(),
            'rejected_krs' => KRS::where('id_mahasiswa', $mahasiswa->id)->rejected()->count(),
            'current_semester_sks' => $currentSemesterKrs?->total_sks ?? 0,
            'total_ipk' => $this->calculateIPK($mahasiswa->id),
            'max_sks_allowed' => $this->getMaxSksAllowed($mahasiswa),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    private function getAuthenticatedMahasiswa(Request $request): ?Mahasiswa
    {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        return Mahasiswa::with('prodi')->where('user_id', $user->id)->first();
    }

    private function getActiveSemester(): ?Semester
    {
        return Semester::with('tahunAkademik:id,tahun_akademik,status_aktif')
            ->select('id', 'id_tahun_akademik', 'nama_semester', 'kode_semester', 'tanggal_mulai', 'tanggal_selesai', 'status')
            ->where('status', 'Aktif')
            ->first();
    }

    private function validateKRSPeriod(Semester $semester): ?JsonResponse
    {
        $today = now()->startOfDay();
        $periodeKrs = PeriodeKrs::where('id_semester', $semester->id)->first();

        if ($periodeKrs) {
            if ($periodeKrs->status !== 'aktif') {
                return response()->json([
                    'success' => false,
                    'message' => 'Periode KRS untuk semester ini belum aktif'
                ], 400);
            }

            if ($today->lt($periodeKrs->tanggal_mulai)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Periode pengajuan KRS belum dimulai'
                ], 400);
            }

            if ($today->gt($periodeKrs->tanggal_selesai)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Periode pengajuan KRS telah berakhir'
                ], 400);
            }

            return null;
        }

        if ($today->lt($semester->tanggal_mulai)) {
            return response()->json([
                'success' => false,
                'message' => 'Periode pengajuan KRS belum dimulai'
            ], 400);
        }

        if ($today->gt($semester->tanggal_selesai)) {
            return response()->json([
                'success' => false,
                'message' => 'Periode pengajuan KRS telah berakhir'
            ], 400);
        }

        return null;
    }

    private function validatePrerequisites(string $mahasiswaId, $mataKuliah): array
    {
        $requirements = [];

        foreach ($mataKuliah->prasyarat ?? [] as $prasyarat) {
            $mkPrasyarat = $prasyarat->mataKuliahPrasyarat;

            if (!$mkPrasyarat) {
                continue;
            }

            $hasPassed = KRSDetail::whereHas('krs', function ($query) use ($mahasiswaId) {
                $query->where('id_mahasiswa', $mahasiswaId)
                    ->where('status_approval', KRS::STATUS_APPROVED);
            })
                ->whereHas('kelasKuliah.kurikulumMataKuliah.mataKuliah', function ($query) use ($mahasiswaId, $mkPrasyarat) {
                    $equivalentCourseIds = $this->curriculumConversionService
                        ->getRecognizedSourceCourseIdsForTarget($mahasiswaId, $mkPrasyarat->id);

                    $query->whereIn('mata_kuliah.id', $equivalentCourseIds);
                })
                ->where('status', KRSDetail::STATUS_LULUS)
                ->where('bobot_nilai', '>=', $prasyarat->min_bobot_nilai)
                ->exists();

            $requirements[] = [
                'id_mata_kuliah_prasyarat' => $mkPrasyarat->id,
                'kode_mk' => $mkPrasyarat->kode_mk,
                'nama_mk' => $mkPrasyarat->nama_mk,
                'min_bobot_nilai' => $prasyarat->min_bobot_nilai,
                'is_passed' => $hasPassed,
            ];
        }

        $missing = array_values(array_filter($requirements, fn($item) => !$item['is_passed']));

        if (count($missing) > 0) {
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

    private function krsRelations(): array
    {
        return [
            'mahasiswa.prodi.kaprodi',
            'mahasiswa.dosenWali',
            'semester.tahunAkademik',
            'details.kelasKuliah.kurikulumMataKuliah.mataKuliah',
            'details.kelasKuliah.jadwal',
            'details.kelasKuliah.dosen_pengajar.dosen',
            'approvedBy',
            'sksOverrideBy',
        ];
    }

    private function loadKRSForResponse(string $id): ?KRS
    {
        return KRS::with($this->krsRelations())->find($id);
    }

    private function transformKRS(KRS $krs): array
    {
        $validationSummary = $this->buildValidationSummary($krs);
        $semesterKe = null;

        if ($krs->mahasiswa && $krs->semester && $krs->semester->tahunAkademik) {
            $semesterKe = $this->hitungSemesterKrs($krs->mahasiswa, $krs->semester);
        }

        $activeSemester = $this->getActiveSemester();
        $details = $this->transformKRSDetails($krs, $semesterKe);
        $packageInsight = $semesterKe ? $this->buildPackageInsightForKrs($krs, $semesterKe) : [
            'summary' => null,
            'unresolved_items' => [],
        ];

        return [
            'id' => $krs->id,
            'id_mahasiswa' => $krs->id_mahasiswa,
            'id_semester' => $krs->id_semester,
            'semester_aktif' => $krs->semester,
            'semester_global_aktif' => $activeSemester,
            'tanggal_pengajuan' => $krs->tanggal_pengajuan,
            'status_approval' => $krs->status_approval,
            'approved_by' => $krs->approved_by,
            'tanggal_approval' => $krs->tanggal_approval,
            'catatan' => $krs->catatan,
            'total_sks' => $krs->total_sks,
            'is_locked' => $krs->is_locked,
            'can_edit' => $krs->isEditable(),
            'can_submit' => $krs->isEditable() && $validationSummary['is_valid'],
            'mahasiswa' => $krs->mahasiswa,
            'semester' => $krs->semester,
            'details' => $details,
            'approved_by_detail' => $krs->approvedBy,
            'sks_override' => [
                'is_active' => (bool) $krs->is_sks_override,
                'reason' => $krs->sks_override_reason,
                'by' => $krs->sksOverrideBy,
                'at' => $krs->sks_override_at,
            ],
            'validation_summary' => $validationSummary,
            'semester_ke' => $semesterKe,
            'package_summary' => $packageInsight['summary'],
            'unresolved_package_items' => $packageInsight['unresolved_items'],
            'created_at' => $krs->created_at,
            'updated_at' => $krs->updated_at,
        ];
    }

    private function transformKRSDetails(KRS $krs, ?int $semesterKe): array
    {
        return ($krs->details ?? collect())
            ->map(function (KRSDetail $detail) use ($semesterKe) {
                $kelas = $detail->kelasKuliah;
                $kurikulumMataKuliah = $kelas?->kurikulumMataKuliah;
                $mataKuliah = $kurikulumMataKuliah?->mataKuliah;
                $kategori = $this->determineDetailCategory($detail, $semesterKe);

                return [
                    ...$detail->toArray(),
                    'sks' => $mataKuliah?->sks ?? 0,
                    'id_mata_kuliah' => $kurikulumMataKuliah?->id_mata_kuliah,
                    'semester_paket' => $kurikulumMataKuliah?->semester_ke,
                    'kategori_pengambilan' => $kategori,
                    'is_repeat' => $kategori === 'ulang',
                ];
            })
            ->values()
            ->all();
    }

    private function determineDetailCategory(KRSDetail $detail, ?int $semesterKe): string
    {
        $semesterPaket = (int) ($detail->kelasKuliah?->kurikulumMataKuliah?->semester_ke ?? 0);

        if ($semesterKe && $semesterPaket === $semesterKe) {
            return 'paket';
        }

        if ($semesterKe && $semesterPaket > 0 && $semesterPaket < $semesterKe) {
            return 'ulang';
        }

        return 'tambahan';
    }

    private function buildPackageInsightForKrs(KRS $krs, int $semesterKe): array
    {
        $mahasiswa = $krs->relationLoaded('mahasiswa') ? $krs->mahasiswa : Mahasiswa::find($krs->id_mahasiswa);
        $semester = $krs->relationLoaded('semester') ? $krs->semester : Semester::find($krs->id_semester);

        $activeKurikulumId = $mahasiswa ? $this->activeCurriculumService->resolveActiveKurikulumId($mahasiswa) : null;

        if (!$mahasiswa || !$semester || !$activeKurikulumId) {
            return [
                'summary' => [
                    'semester_ke' => $semesterKe,
                    'generated_count' => 0,
                    'generated_sks' => 0,
                    'repeat_count' => 0,
                    'repeat_sks' => 0,
                    'unresolved_count' => 0,
                ],
                'unresolved_items' => [],
            ];
        }

        $selectedMataKuliahIds = $this->getSelectedMataKuliahIds($krs);
        $details = $krs->relationLoaded('details') ? $krs->details : $krs->details()->with('kelasKuliah.kurikulumMataKuliah.mataKuliah')->get();

        $generatedCount = 0;
        $generatedSks = 0;
        $repeatCount = 0;
        $repeatSks = 0;

        foreach ($details as $detail) {
            $kategori = $this->determineDetailCategory($detail, $semesterKe);
            $sks = (int) ($detail->kelasKuliah?->kurikulumMataKuliah?->mataKuliah?->sks ?? 0);

            if ($kategori === 'paket') {
                $generatedCount++;
                $generatedSks += $sks;
            } elseif ($kategori === 'ulang') {
                $repeatCount++;
                $repeatSks += $sks;
            }
        }

        $packageItems = $this->getPackageItemsForSemester($mahasiswa, $semester, $semesterKe);
        $unresolvedItems = [];

        if ($packageItems->isEmpty()) {
            $unresolvedItems[] = [
                'id_struktur_operasional' => $activeKurikulumId,
                'id_kurikulum' => $activeKurikulumId,
                'reason' => $this->buildMissingPackageReason($mahasiswa, $semester, $semesterKe, $activeKurikulumId),
            ];
        }

        foreach ($packageItems as $packageItem) {
            $mataKuliah = $packageItem->mataKuliah;

            if (!$mataKuliah) {
                continue;
            }

            if (in_array($mataKuliah->id, $selectedMataKuliahIds, true)) {
                continue;
            }

            $candidateClasses = KelasKuliah::query()
                ->where('id_semester', $semester->id)
                ->where('id_prodi', $mahasiswa->id_prodi)
                ->where('id_kurikulum_mata_kuliah', $packageItem->id)
                ->with([
                    'kurikulumMataKuliah.mataKuliah',
                    'jadwal',
                ])
                ->orderBy('nama_kelas')
                ->get();

            $reason = $candidateClasses->isEmpty()
                ? 'Kelas kuliah untuk paket semester ini belum tersedia'
                : $this->resolvePackageClassFailureReason(
                    $candidateClasses,
                    $details->pluck('kelasKuliah')->filter()->values(),
                    (int) $krs->total_sks,
                    $this->getMaxSksAllowed($mahasiswa)
                );

            $unresolvedItems[] = [
                'id_kurikulum_mata_kuliah' => $packageItem->id,
                'id_mata_kuliah' => $mataKuliah->id,
                'kode_mk' => $mataKuliah->kode_mk,
                'nama_mk' => $mataKuliah->nama_mk,
                'reason' => $reason,
            ];
        }

        return [
            'summary' => [
                'semester_ke' => $semesterKe,
                'generated_count' => $generatedCount,
                'generated_sks' => $generatedSks,
                'repeat_count' => $repeatCount,
                'repeat_sks' => $repeatSks,
                'unresolved_count' => count($unresolvedItems),
            ],
            'unresolved_items' => $unresolvedItems,
        ];
    }

    private function buildValidationSummary(KRS $krs): array
    {
        $mahasiswa = $krs->relationLoaded('mahasiswa') ? $krs->mahasiswa : Mahasiswa::find($krs->id_mahasiswa);
        $details = $krs->relationLoaded('details') ? $krs->details : $krs->details()->with('kelasKuliah.jadwal')->get();
        $totalSks = $krs->total_sks ?? $krs->calculateTotalSks();
        $maxSks = $mahasiswa ? $this->getMaxSksAllowed($mahasiswa) : 0;
        $hasScheduleConflict = false;

        foreach ($details as $detail) {
            $kelas = $detail->kelasKuliah;
            if (!$kelas) {
                continue;
            }

            if ($this->hasJadwalKonflikForDetailCollection($details, $detail->id_kelas_kuliah)) {
                $hasScheduleConflict = true;
                break;
            }
        }

        return [
            'status' => $krs->status_approval,
            'total_matkul' => $details->count(),
            'total_sks' => $totalSks,
            'max_sks_allowed' => $maxSks,
            'is_sks_override' => (bool) $krs->is_sks_override,
            'sks_override_reason' => $krs->sks_override_reason,
            'remaining_sks' => max($maxSks - $totalSks, 0),
            'has_items' => $details->count() > 0,
            'max_sks_ok' => $totalSks <= $maxSks || (bool) $krs->is_sks_override,
            'max_sks_actual_ok' => $totalSks <= $maxSks,
            'schedule_conflict' => $hasScheduleConflict,
            'can_edit' => $krs->isEditable(),
            'can_submit' => $krs->isEditable() && $details->count() > 0 && ($totalSks <= $maxSks || (bool) $krs->is_sks_override) && !$hasScheduleConflict,
            'is_valid' => $details->count() > 0 && ($totalSks <= $maxSks || (bool) $krs->is_sks_override) && !$hasScheduleConflict,
        ];
    }

    private function hasJadwalKonflikForDetailCollection($details, string $currentKelasId): bool
    {
        $currentDetail = $details->firstWhere('id_kelas_kuliah', $currentKelasId);
        if (!$currentDetail || !$currentDetail->kelasKuliah) {
            return false;
        }

        $currentJadwals = $currentDetail->kelasKuliah->jadwal;

        foreach ($details as $detail) {
            if ($detail->id_kelas_kuliah === $currentKelasId || !$detail->kelasKuliah) {
                continue;
            }

            foreach ($currentJadwals as $currentJadwal) {
                foreach ($detail->kelasKuliah->jadwal as $otherJadwal) {
                    if (
                        $currentJadwal->hari === $otherJadwal->hari &&
                        $this->isTimeOverlap(
                            $currentJadwal->jam_mulai,
                            $currentJadwal->jam_selesai,
                            $otherJadwal->jam_mulai,
                            $otherJadwal->jam_selesai
                        )
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function getMaxSksAllowed(Mahasiswa $mahasiswa): int
    {
        return 24;
    }

    private function canForceSksOverride(Request $request): bool
    {
        $user = $request->user();

        if (!$user || !method_exists($user, 'hasAnyRole')) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'baak', 'kaprodi']);
    }

    private function calculateIPK(string $mahasiswaId): float
    {
        $totalBobot = 0;
        $totalSks = 0;

        $krsHistory = KRS::where('id_mahasiswa', $mahasiswaId)
            ->where('status_approval', KRS::STATUS_APPROVED)
            ->with('details')
            ->get();

        foreach ($krsHistory as $krs) {
            foreach ($krs->details as $detail) {
                if ($detail->bobot_nilai && $detail->status === KRSDetail::STATUS_LULUS) {
                    $totalBobot += $detail->bobot_nilai * $detail->sks;
                    $totalSks += $detail->sks;
                }
            }
        }

        return $totalSks > 0 ? round($totalBobot / $totalSks, 2) : 0;
    }

    private function generatePackageKrs(KRS $krs, Mahasiswa $mahasiswa, Semester $semester): array
    {
        $semesterKe = $this->hitungSemesterKrs($mahasiswa, $semester);
        $maxSks = $this->getMaxSksAllowed($mahasiswa);
        $generatedSks = 0;
        $generatedCount = 0;
        $unresolvedItems = [];
        $selectedClasses = collect();

        $activeKurikulumId = $this->activeCurriculumService->resolveActiveKurikulumId($mahasiswa);
        $curriculumContext = $this->activeCurriculumService->resolveCurriculumContext($mahasiswa);

        if (!$activeKurikulumId) {
            return [
                'summary' => [
                    'semester_ke' => $semesterKe,
                    'kurikulum_context' => $curriculumContext,
                    'id_struktur_operasional' => $activeKurikulumId,
                    'id_kurikulum_operasional' => $activeKurikulumId,
                    'generated_count' => 0,
                    'generated_sks' => 0,
                    'unresolved_count' => 1,
                ],
                'unresolved_items' => [[
                    'reason' => 'Mahasiswa belum memiliki kurikulum operasional untuk semester berjalan',
                ]],
            ];
        }

        $packageItems = $this->getPackageItemsForSemester($mahasiswa, $semester, $semesterKe);

        if ($packageItems->isEmpty()) {
            return [
                'summary' => [
                    'semester_ke' => $semesterKe,
                    'kurikulum_context' => $curriculumContext,
                    'generated_count' => 0,
                    'generated_sks' => 0,
                    'unresolved_count' => 1,
                ],
                'unresolved_items' => [[
                    'id_struktur_operasional' => $activeKurikulumId,
                    'id_kurikulum' => $activeKurikulumId,
                    'reason' => $this->buildMissingPackageReason($mahasiswa, $semester, $semesterKe, $activeKurikulumId),
                ]],
            ];
        }

        foreach ($packageItems as $packageItem) {
            $mataKuliah = $packageItem->mataKuliah;

            if (!$mataKuliah) {
                $unresolvedItems[] = [
                    'id_kurikulum_mata_kuliah' => $packageItem->id,
                    'reason' => 'Data mata kuliah pada kurikulum tidak ditemukan',
                ];
                continue;
            }

            $candidateClasses = KelasKuliah::where('id_semester', $semester->id)
                ->where('id_prodi', $mahasiswa->id_prodi)
                ->where('id_kurikulum_mata_kuliah', $packageItem->id)
                ->with([
                    'kurikulumMataKuliah.mataKuliah',
                    'jadwal',
                ])
                ->orderBy('nama_kelas')
                ->get();

            if ($candidateClasses->isEmpty()) {
                $unresolvedItems[] = [
                    'id_kurikulum_mata_kuliah' => $packageItem->id,
                    'id_mata_kuliah' => $mataKuliah->id,
                    'kode_mk' => $mataKuliah->kode_mk,
                    'nama_mk' => $mataKuliah->nama_mk,
                    'reason' => 'Kelas kuliah untuk paket semester ini belum tersedia',
                ];
                continue;
            }

            $selectedClass = $candidateClasses->first(function (KelasKuliah $candidate) use ($selectedClasses, $generatedSks, $maxSks) {
                $candidateSks = $candidate->kurikulumMataKuliah->mataKuliah->sks ?? 0;

                if ($candidate->isPenuh()) {
                    return false;
                }

                if (($generatedSks + $candidateSks) > $maxSks) {
                    return false;
                }

                return !$this->hasClassScheduleConflict($selectedClasses, $candidate);
            });

            if (!$selectedClass) {
                $reason = $this->resolvePackageClassFailureReason($candidateClasses, $selectedClasses, $generatedSks, $maxSks);

                $unresolvedItems[] = [
                    'id_kurikulum_mata_kuliah' => $packageItem->id,
                    'id_mata_kuliah' => $mataKuliah->id,
                    'kode_mk' => $mataKuliah->kode_mk,
                    'nama_mk' => $mataKuliah->nama_mk,
                    'reason' => $reason,
                ];
                continue;
            }

            KRSDetail::create([
                'id_krs' => $krs->id,
                'id_kelas_kuliah' => $selectedClass->id,
                'status' => KRSDetail::STATUS_TERDAFTAR,
            ]);

            $selectedClasses->push($selectedClass);
            $generatedCount++;
            $generatedSks += $selectedClass->kurikulumMataKuliah->mataKuliah->sks ?? 0;
        }

        $krs->update(['total_sks' => $krs->calculateTotalSks()]);

        return [
            'summary' => [
                'semester_ke' => $semesterKe,
                'kurikulum_context' => $curriculumContext,
                'id_struktur_operasional' => $activeKurikulumId,
                'id_kurikulum_operasional' => $activeKurikulumId,
                'generated_count' => $generatedCount,
                'generated_sks' => $generatedSks,
                'unresolved_count' => count($unresolvedItems),
            ],
            'unresolved_items' => $unresolvedItems,
        ];
    }

    private function getSelectedMataKuliahIds(KRS $krs): array
    {
        return $krs->details()
            ->whereHas('kelasKuliah.kurikulumMataKuliah')
            ->with('kelasKuliah.kurikulumMataKuliah')
            ->get()
            ->map(fn(KRSDetail $detail) => $detail->kelasKuliah?->kurikulumMataKuliah?->id_mata_kuliah)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function getPackageItemsForSemester(Mahasiswa $mahasiswa, Semester $semester, int $semesterKe): Collection
    {
        return $this->activeCurriculumService->resolvePackageItemsForSemester($mahasiswa, $semesterKe);
    }

    private function buildMissingPackageReason(
        Mahasiswa $mahasiswa,
        Semester $semester,
        int $semesterKe,
        ?string $activeKurikulumId
    ): string {
        $availableClassCount = KelasKuliah::query()
            ->where('id_semester', $semester->id)
            ->where('id_prodi', $mahasiswa->id_prodi)
            ->whereHas('kurikulumMataKuliah', function ($query) use ($semesterKe) {
                $query->where('semester_ke', $semesterKe);
            })
            ->count();

        if ($availableClassCount > 0) {
            return "Ada {$availableClassCount} kelas kuliah untuk semester {$semesterKe}, tetapi belum terhubung ke kurikulum operasional mahasiswa";
        }

        if (!$activeKurikulumId) {
            return "Mahasiswa belum memiliki kurikulum operasional untuk membentuk paket semester {$semesterKe}";
        }

        return "Belum ada mata kuliah paket yang terdefinisi untuk semester {$semesterKe} pada kurikulum operasional mahasiswa";
    }

    private function hasClassScheduleConflict(Collection $selectedClasses, KelasKuliah $candidate): bool
    {
        foreach ($selectedClasses as $selectedClass) {
            foreach ($candidate->jadwal as $candidateJadwal) {
                foreach ($selectedClass->jadwal as $selectedJadwal) {
                    if (
                        $candidateJadwal->hari === $selectedJadwal->hari &&
                        $this->isTimeOverlap(
                            $candidateJadwal->jam_mulai,
                            $candidateJadwal->jam_selesai,
                            $selectedJadwal->jam_mulai,
                            $selectedJadwal->jam_selesai
                        )
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function resolvePackageClassFailureReason(
        Collection $candidateClasses,
        Collection $selectedClasses,
        int $generatedSks,
        int $maxSks
    ): string {
        $hasNonFullClass = $candidateClasses->contains(fn(KelasKuliah $candidate) => !$candidate->isPenuh());

        if (!$hasNonFullClass) {
            return 'Semua kelas untuk mata kuliah paket ini sudah penuh';
        }

        $hasWithinSksLimit = $candidateClasses->contains(function (KelasKuliah $candidate) use ($generatedSks, $maxSks) {
            $candidateSks = $candidate->kurikulumMataKuliah->mataKuliah->sks ?? 0;

            return ($generatedSks + $candidateSks) <= $maxSks;
        });

        if (!$hasWithinSksLimit) {
            return "Penambahan mata kuliah paket ini akan melebihi batas maksimal {$maxSks} SKS";
        }

        $hasWithoutConflict = $candidateClasses->contains(function (KelasKuliah $candidate) use ($selectedClasses) {
            return !$candidate->isPenuh() && !$this->hasClassScheduleConflict($selectedClasses, $candidate);
        });

        if (!$hasWithoutConflict) {
            return 'Semua pilihan kelas untuk mata kuliah paket ini bentrok dengan paket yang sudah tergenerate';
        }

        return 'Kelas kuliah paket tidak dapat dipilih secara otomatis';
    }

    // private function getCurrentMahasiswaSemester(Mahasiswa $mahasiswa): int
    // {
    //     $angkatan = $mahasiswa->angkatan;
    //     $currentYear = date('Y');

    //     return ($currentYear - $angkatan) * 2 + 1;
    // }

    private function hitungSemesterBerjalan(Mahasiswa $mahasiswa): int
    {
        $semesterAktif = $this->getActiveSemester();
        $tahunSekarang = (int) substr($semesterAktif->tahunAkademik->tahun_akademik, 0, 4);
        $digitPeriode = (strtolower(trim($semesterAktif->nama_semester)) === 'ganjil') ? 1 : 2;
        $selisihTahun = $tahunSekarang - $mahasiswa->angkatan;

        return ($selisihTahun * 2) + $digitPeriode;
    }

    private function hitungSemesterKrs(Mahasiswa $mahasiswa, Semester $semester): int
    {
        $tahunAkademik = $semester->tahunAkademik;
        $tahunMulai = (int) substr((string) $tahunAkademik->tahun_akademik, 0, 4);
        $digitPeriode = strtolower(trim((string) $semester->nama_semester)) === 'ganjil' ? 1 : 2;
        $selisihTahun = $tahunMulai - (int) $mahasiswa->angkatan;

        return max(1, ($selisihTahun * 2) + $digitPeriode);
    }

    private function hasJadwalKonflik(KRS $krs, KelasKuliah $newKelas): bool
    {
        $existingDetails = KRSDetail::where('id_krs', $krs->id)
            ->with('kelasKuliah.jadwal')
            ->get();

        $newJadwals = $newKelas->jadwal;

        foreach ($existingDetails as $detail) {
            if (!$detail->kelasKuliah) {
                continue;
            }

            $existingJadwals = $detail->kelasKuliah->jadwal;

            foreach ($newJadwals as $newJadwal) {
                foreach ($existingJadwals as $existingJadwal) {
                    if (
                        $newJadwal->hari === $existingJadwal->hari &&
                        $this->isTimeOverlap(
                            $newJadwal->jam_mulai,
                            $newJadwal->jam_selesai,
                            $existingJadwal->jam_mulai,
                            $existingJadwal->jam_selesai
                        )
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function isSemesterBefore(?Semester $candidate, Semester $reference): bool
    {
        if (!$candidate || !$candidate->tahunAkademik || !$reference->tahunAkademik) {
            return true;
        }

        $candidateYear = (int) substr((string) $candidate->tahunAkademik->tahun_akademik, 0, 4);
        $referenceYear = (int) substr((string) $reference->tahunAkademik->tahun_akademik, 0, 4);

        if ($candidateYear !== $referenceYear) {
            return $candidateYear < $referenceYear;
        }

        return $this->semesterPeriodWeight($candidate->nama_semester) < $this->semesterPeriodWeight($reference->nama_semester);
    }

    private function semesterPeriodWeight(?string $semesterName): int
    {
        $normalized = strtolower(trim((string) $semesterName));

        return str_contains($normalized, 'genap') ? 2 : 1;
    }

    private function isTimeOverlap(string $start1, string $end1, string $start2, string $end2): bool
    {
        return ($start1 < $end2) && ($start2 < $end1);
    }
}
