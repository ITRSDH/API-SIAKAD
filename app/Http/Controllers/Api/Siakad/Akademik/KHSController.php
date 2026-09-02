<?php

namespace App\Http\Controllers\Api\Siakad\Akademik;

use App\Http\Controllers\Controller;
use App\Models\Akademik\KHS;
use App\Models\Akademik\KHSDetail;
use App\Models\Akademik\KRS;
use App\Models\Akademik\KRSDetail;
use App\Models\Akademik\PenilaianKelas;
use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\Semester;
use App\Services\ActiveCurriculumService;
use App\Services\Khs\KhsCalculationService;
use App\Services\Khs\KhsManualUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class KHSController extends Controller
{
    private ?bool $hasKeteranganColumn = null;

    public function __construct(
        private readonly KhsCalculationService $calculationService,
        private readonly KhsManualUpdateService $manualUpdateService,
        private readonly ActiveCurriculumService $activeCurriculumService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $authenticatedMahasiswa = $this->getAuthenticatedMahasiswa($request);

        if ($authenticatedMahasiswa) {
            $request->merge([
                'id_mahasiswa' => $authenticatedMahasiswa->id,
            ]);
        }

        $query = KHS::with([
            'mahasiswa:id,nim,nama_mahasiswa,angkatan,id_prodi',
            'semester.tahunAkademik:id,tahun_akademik',
        ])->orderByDesc('generated_at');

        if ($request->filled('id_mahasiswa')) {
            $query->where('id_mahasiswa', $request->id_mahasiswa);
        }

        if ($request->filled('id_semester')) {
            $query->where('id_semester', $request->id_semester);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
            'meta' => [
                'mahasiswa' => $authenticatedMahasiswa ? [
                    'id' => $authenticatedMahasiswa->id,
                    'nim' => $authenticatedMahasiswa->nim,
                    'nama_mahasiswa' => $authenticatedMahasiswa->nama_mahasiswa,
                ] : null,
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $query = KHS::with([
            'mahasiswa:id,nim,nama_mahasiswa,angkatan,id_prodi',
            'mahasiswa.prodi:id,nama_prodi,id_kaprodi,jenjang_pendidikan',
            'mahasiswa.prodi.kaprodi:id,nama_dosen,nidn',
            'semester.tahunAkademik:id,tahun_akademik',
            'updater:id,name',
            'finalizer:id,name',
            'details',
        ]);

        $authenticatedMahasiswa = $this->getAuthenticatedMahasiswa(request());
        if ($authenticatedMahasiswa) {
            $query->where('id_mahasiswa', $authenticatedMahasiswa->id);
        }

        $khs = $query->find($id);

        if (!$khs) {
            return response()->json([
                'success' => false,
                'message' => 'KHS tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $khs,
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $this->normalizeDecimalInputs($request, ['ipk']);

        // Client (misal jQuery $.post) bisa mengirim "true"/"false" sebagai string;
        // rule 'boolean' hanya menerima [true,false,0,1,"0","1"].
        if ($request->exists('is_final')) {
            $request->merge([
                'is_final' => $this->normalizeBooleanInput($request->input('is_final')),
            ]);
        }

        $validated = $request->validate([
            'id_mahasiswa' => 'required|uuid|exists:mahasiswa,id',
            'id_semester' => 'required|uuid|exists:semester,id',
            'is_final' => 'nullable|boolean',
            'ipk' => 'nullable|numeric|min:0|max:4',
        ]);

        $mahasiswa = Mahasiswa::find($validated['id_mahasiswa']);
        $semester = Semester::with('tahunAkademik')->find($validated['id_semester']);

        if (!$mahasiswa || !$semester) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa atau semester tidak ditemukan',
            ], 404);
        }

        $krs = KRS::with([
            'details.kelasKuliah.penilaianKelas',
            'details.kelasKuliah.kurikulumMataKuliah.mataKuliah',
            'details.nilaiKomponen',
        ])
            ->where('id_mahasiswa', $mahasiswa->id)
            ->where('id_semester', $semester->id)
            ->where('status_approval', KRS::STATUS_APPROVED)
            ->first();

        if (!$krs) {
            return response()->json([
                'success' => false,
                'message' => 'KRS approved untuk mahasiswa dan semester tersebut tidak ditemukan',
            ], 404);
        }

        if (!$krs->is_locked) {
            return response()->json([
                'success' => false,
                'message' => 'KRS harus dalam kondisi terkunci sebelum KHS dapat digenerate',
            ], 422);
        }

        if ($validationError = $this->validateKrsForKhs($krs)) {
            return $validationError;
        }

        $existingKhs = KHS::where('id_mahasiswa', $mahasiswa->id)
            ->where('id_semester', $semester->id)
            ->first();

        if ($existingKhs?->is_final) {
            return response()->json([
                'success' => false,
                'message' => 'KHS yang sudah difinalisasi tidak dapat digenerate ulang',
            ], 422);
        }

        $snapshot = $this->buildSemesterSnapshot($mahasiswa->id, $semester->id, $krs, $validated['ipk'] ?? null);

        if ($snapshot['requires_manual_ipk'] && $snapshot['summary']['ipk'] === null) {
            return response()->json([
                'success' => false,
                'message' => 'IPK manual wajib diisi untuk generate KHS semester di atas semester 1.',
                'data' => [
                    'semester_ke' => $snapshot['semester_ke'],
                    'requires_manual_ipk' => true,
                ],
            ], 422);
        }

        $khs = DB::transaction(function () use ($validated, $snapshot, $mahasiswa, $semester) {
            $khs = KHS::updateOrCreate(
                [
                    'id_mahasiswa' => $mahasiswa->id,
                    'id_semester' => $semester->id,
                ],
                $this->withOptionalKeterangan([
                    'total_sks_diambil' => $snapshot['summary']['total_sks_diambil'],
                    'total_sks_lulus' => $snapshot['summary']['total_sks_lulus'],
                    'ips' => $snapshot['summary']['ips'],
                    'ipk' => $snapshot['summary']['ipk'],
                    'keterangan' => $snapshot['summary']['keterangan'],
                    'is_final' => $validated['is_final'] ?? false,
                    'generated_at' => now(),
                ])
            );

            KHSDetail::where('id_khs', $khs->id)->delete();

            foreach ($snapshot['details'] as $detail) {
                KHSDetail::create([
                    'id_khs' => $khs->id,
                    'id_krs_detail' => $detail['id_krs_detail'],
                    'id_kelas_kuliah' => $detail['id_kelas_kuliah'],
                    'id_mata_kuliah' => $detail['id_mata_kuliah'],
                    'kode_mk' => $detail['kode_mk'],
                    'nama_mk' => $detail['nama_mk'],
                    'sks' => $detail['sks'],
                    'nilai_akhir' => $detail['nilai_akhir'],
                    'nilai_huruf' => $detail['nilai_huruf'],
                    'bobot_nilai' => $detail['bobot_nilai'],
                    'mutu' => $detail['mutu'],
                    'status' => $detail['status'],
                ]);
            }

            return $khs->load([
                'mahasiswa:id,nim,nama_mahasiswa,angkatan,id_prodi',
                'semester.tahunAkademik:id,tahun_akademik',
                'details',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'KHS berhasil digenerate',
            'data' => $khs,
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $this->normalizeDecimalInputs($request, ['ipk']);

        $validated = $request->validate([
            'id_mahasiswa' => 'required|uuid|exists:mahasiswa,id',
            'id_semester' => 'required|uuid|exists:semester,id',
            'ipk' => 'nullable|numeric|min:0|max:4',
        ]);

        $krs = KRS::with([
            'details.kelasKuliah.penilaianKelas',
            'details.kelasKuliah.kurikulumMataKuliah.mataKuliah',
            'details.nilaiKomponen',
            'semester.tahunAkademik',
        ])
            ->where('id_mahasiswa', $validated['id_mahasiswa'])
            ->where('id_semester', $validated['id_semester'])
            ->where('status_approval', KRS::STATUS_APPROVED)
            ->first();

        if (!$krs) {
            return response()->json([
                'success' => false,
                'message' => 'KRS approved untuk mahasiswa dan semester tersebut tidak ditemukan',
            ], 404);
        }

        if (!$krs->is_locked) {
            return response()->json([
                'success' => false,
                'message' => 'KRS harus dalam kondisi terkunci sebelum KHS dapat dipreview',
            ], 422);
        }

        if ($validationError = $this->validateKrsForKhs($krs)) {
            return $validationError;
        }

        $snapshot = $this->buildSemesterSnapshot($validated['id_mahasiswa'], $validated['id_semester'], $krs, $validated['ipk'] ?? null);

        if ($snapshot['requires_manual_ipk'] && $snapshot['summary']['ipk'] === null) {
            return response()->json([
                'success' => false,
                'message' => 'IPK manual wajib diisi untuk preview KHS semester di atas semester 1.',
                'data' => [
                    'semester_ke' => $snapshot['semester_ke'],
                    'requires_manual_ipk' => true,
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $snapshot,
        ]);
    }

    public function updateDetail(Request $request, string $id, string $detailId): JsonResponse
    {
        $this->normalizeDecimalInputs($request, ['nilai_akhir', 'bobot_nilai', 'mutu', 'ipk']);

        $validated = $request->validate([
            'nilai_akhir' => 'nullable|numeric|min:0|max:100',
            'nilai_huruf' => 'nullable|string|max:2',
            'bobot_nilai' => 'nullable|numeric|min:0',
            'mutu' => 'nullable|numeric|min:0',
            'ipk' => 'nullable|numeric|min:0|max:4',
            'reason' => 'nullable|string|max:1000',
        ]);

        $query = KHS::query()->with(['details', 'mahasiswa', 'semester.tahunAkademik', 'revisions.creator:id,name']);
        $authenticatedMahasiswa = $this->getAuthenticatedMahasiswa($request);
        if ($authenticatedMahasiswa) {
            $query->where('id_mahasiswa', $authenticatedMahasiswa->id);
        }

        $khs = $query->find($id);
        if (!$khs) {
            return response()->json([
                'success' => false,
                'message' => 'KHS tidak ditemukan',
            ], 404);
        }

        $detail = $khs->details->firstWhere('id', $detailId);
        if (!$detail) {
            return response()->json([
                'success' => false,
                'message' => 'Detail KHS tidak ditemukan',
            ], 404);
        }

        try {
            $result = $this->manualUpdateService->updateDetail($khs, $detail, $validated, $request->user()?->id);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function updateSummary(Request $request, string $id): JsonResponse
    {
        $this->normalizeDecimalInputs($request, ['ipk']);

        $validated = $request->validate([
            'ipk' => 'nullable|numeric|min:0|max:4',
            'reason' => 'nullable|string|max:1000',
        ]);

        $query = KHS::query()->with(['details', 'mahasiswa', 'semester.tahunAkademik', 'revisions.creator:id,name']);
        $authenticatedMahasiswa = $this->getAuthenticatedMahasiswa($request);
        if ($authenticatedMahasiswa) {
            $query->where('id_mahasiswa', $authenticatedMahasiswa->id);
        }

        $khs = $query->find($id);
        if (!$khs) {
            return response()->json([
                'success' => false,
                'message' => 'KHS tidak ditemukan',
            ], 404);
        }

        try {
            $result = $this->manualUpdateService->updateSummary($khs, $validated, $request->user()?->id);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function finalize(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $query = KHS::query()->with(['details', 'mahasiswa', 'semester.tahunAkademik', 'revisions.creator:id,name']);
        $authenticatedMahasiswa = $this->getAuthenticatedMahasiswa($request);
        if ($authenticatedMahasiswa) {
            $query->where('id_mahasiswa', $authenticatedMahasiswa->id);
        }

        $khs = $query->find($id);
        if (!$khs) {
            return response()->json([
                'success' => false,
                'message' => 'KHS tidak ditemukan',
            ], 404);
        }

        $result = $this->manualUpdateService->finalize($khs, $request->user()?->id, $validated['reason'] ?? null);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    private function buildSemesterSnapshot(string $mahasiswaId, string $semesterId, KRS $krs, ?float $manualIpk = null): array
    {
        $mahasiswa = $krs->relationLoaded('mahasiswa') ? $krs->mahasiswa : Mahasiswa::find($mahasiswaId);
        $curriculumContext = $mahasiswa
            ? $this->activeCurriculumService->resolveCurriculumContext($mahasiswa)
            : [
                'id_kurikulum' => null,
                'id_struktur_operasional' => null,
                'id_kurikulum_operasional' => null,
                'struktur_operasional' => null,
            ];

        $details = $this->collectCountedKhsDetails($krs->details)->map(function (KRSDetail $detail) {
            return [
                'id_krs_detail' => $detail->id,
                'id_kelas_kuliah' => $detail->id_kelas_kuliah,
                'id_mata_kuliah' => $detail->resolveMataKuliahId(),
                'kode_mk' => $detail->kode_mata_kuliah,
                'nama_mk' => $detail->nama_mata_kuliah,
                'sks' => $detail->sks,
                'nilai_akhir' => $detail->nilai_akhir,
                'nilai_huruf' => $detail->nilai_huruf,
                'mutu' => $detail->resolveMutuValue(),
                'bobot_nilai' => $detail->resolveWeightedBobotNilaiValue(),
                'status' => $detail->status,
            ];
        })->values();
        $summary = $this->calculationService->calculateSummaryFromKrsDetails($krs->details);
        $semesterKe = $this->resolveSemesterKe($krs);
        $requiresManualIpk = $semesterKe > 1;
        $ipk = $requiresManualIpk
            ? ($manualIpk !== null ? round((float) $manualIpk, 2) : null)
            : $summary['ips'];

        return [
            'semester_ke' => $semesterKe,
            'requires_manual_ipk' => $requiresManualIpk,
            'summary' => [
                'id_mahasiswa' => $mahasiswaId,
                'id_semester' => $semesterId,
                'id_struktur_operasional' => $curriculumContext['id_struktur_operasional'] ?? null,
                'id_kurikulum_operasional' => $curriculumContext['id_kurikulum_operasional'] ?? null,
                'total_sks_diambil' => $summary['total_sks_diambil'],
                'total_sks_lulus' => $summary['total_sks_lulus'],
                'ips' => $summary['ips'],
                'ipk' => $ipk,
                'keterangan' => $summary['keterangan'],
            ],
            'kurikulum_context' => $curriculumContext,
            'details' => $details,
        ];
    }

    private function resolveSemesterKe(KRS $krs): int
    {
        $semesterKe = $krs->details
            ->pluck('kelasKuliah.kurikulumMataKuliah.semester_ke')
            ->filter(fn($value) => $value !== null)
            ->map(fn($value) => (int) $value)
            ->values();

        return $semesterKe->isNotEmpty() ? (int) $semesterKe->max() : 1;
    }

    private function validateKrsForKhs(KRS $krs): ?JsonResponse
    {
        $details = $krs->details;

        $pendingDetails = $details
            ->filter(fn(KRSDetail $detail) => $detail->status === KRSDetail::STATUS_TERDAFTAR)
            ->values();

        if ($pendingDetails->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'KHS belum dapat diproses karena masih ada mata kuliah yang belum memiliki hasil studi final',
                'data' => [
                    'pending_krs_detail_ids' => $pendingDetails->pluck('id')->values(),
                ],
            ], 422);
        }

        $countedDetails = $this->collectCountedKhsDetails($details);

        if ($countedDetails->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada hasil studi final yang dapat dimasukkan ke KHS',
            ], 422);
        }

        $unpublishedClassDetails = $countedDetails
            ->filter(function (KRSDetail $detail) {
                $workflow = $detail->kelasKuliah?->penilaianKelas;
                $hasNilaiKomponen = $detail->relationLoaded('nilaiKomponen')
                    ? $detail->nilaiKomponen->isNotEmpty()
                    : $detail->nilaiKomponen()->exists();

                if (!$workflow || !$hasNilaiKomponen) {
                    return false;
                }

                return !$workflow->isPublished();
            })
            ->values();

        if ($unpublishedClassDetails->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'KHS belum dapat diproses karena masih ada kelas dengan penilaian yang belum dipublikasikan',
                'data' => [
                    'unpublished_krs_detail_ids' => $unpublishedClassDetails->pluck('id')->values(),
                ],
            ], 422);
        }

        $incompleteDetails = $countedDetails
            ->filter(fn(KRSDetail $detail) => !$detail->isFinalScored())
            ->values();

        if ($incompleteDetails->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'KHS belum dapat diproses karena masih ada nilai akhir yang belum lengkap',
                'data' => [
                    'incomplete_krs_detail_ids' => $incompleteDetails->pluck('id')->values(),
                ],
            ], 422);
        }

        return null;
    }

    private function collectCountedKhsDetails(Collection $details): Collection
    {
        return $details
            ->filter(fn(KRSDetail $detail) => $detail->isCountedInKhs())
            ->values();
    }

    private function getAuthenticatedMahasiswa(Request $request): ?Mahasiswa
    {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        return Mahasiswa::where('user_id', $user->id)->first();
    }

    private function withOptionalKeterangan(array $attributes): array
    {
        if ($this->hasKeteranganColumn()) {
            return $attributes;
        }

        unset($attributes['keterangan']);

        return $attributes;
    }

    private function hasKeteranganColumn(): bool
    {
        return $this->hasKeteranganColumn ??= Schema::hasColumn('khs', 'keterangan');
    }

    private function normalizeBooleanInput(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '1', 'true', 'on', 'yes' => true,
                '0', 'false', 'off', 'no' => false,
                default => $value,
            };
        }

        return (bool) $value;
    }

    private function normalizeDecimalInputs(Request $request, array $keys): void
    {
        $normalized = [];

        foreach ($keys as $key) {
            if (!$request->exists($key)) {
                continue;
            }

            $value = $request->input($key);

            if ($value === null || $value === '') {
                $normalized[$key] = $value;
                continue;
            }

            if (is_string($value)) {
                $candidate = trim($value);
                $candidate = str_replace(' ', '', $candidate);

                if (str_contains($candidate, ',') && str_contains($candidate, '.')) {
                    $lastComma = strrpos($candidate, ',');
                    $lastDot = strrpos($candidate, '.');

                    if ($lastComma !== false && $lastDot !== false) {
                        if ($lastComma > $lastDot) {
                            $candidate = str_replace('.', '', $candidate);
                            $candidate = str_replace(',', '.', $candidate);
                        } else {
                            $candidate = str_replace(',', '', $candidate);
                        }
                    }
                } elseif (str_contains($candidate, ',')) {
                    $candidate = str_replace(',', '.', $candidate);
                }

                $normalized[$key] = $candidate;
            }
        }

        if (!empty($normalized)) {
            $request->merge($normalized);
        }
    }
}
