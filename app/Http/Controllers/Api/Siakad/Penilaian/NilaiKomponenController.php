<?php

namespace App\Http\Controllers\Api\Siakad\Penilaian;

use App\Http\Controllers\Controller;
use App\Models\Akademik\KRSDetail;
use App\Models\Akademik\KomponenPenilaian;
use App\Models\Akademik\NilaiKomponen;
use App\Models\Akademik\PenilaianKelas;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\KelasKuliah;
use App\Services\AttendanceEligibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NilaiKomponenController extends Controller
{
    private const REQUIRED_TOTAL_BOBOT = 100.00;

    public function __construct(
        private readonly AttendanceEligibilityService $attendanceEligibilityService
    ) {
    }

    public function index(string $id_kelas_kuliah): JsonResponse
    {
        $kelas = KelasKuliah::with([
            'komponenPenilaian',
            'krsDetail.krs.mahasiswa',
            'krsDetail.nilaiKomponen.komponenPenilaian',
        ])->find($id_kelas_kuliah);

        if (!$kelas) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas kuliah tidak ditemukan',
            ], 404);
        }

        $workflow = $this->getOrCreatePenilaianKelas($kelas);

        $details = $kelas->krsDetail->where('status', KRSDetail::STATUS_TERDAFTAR)->values();

        $rows = $details->map(function (KRSDetail $detail) use ($kelas) {
            $nilaiMap = $detail->nilaiKomponen->keyBy('id_komponen_penilaian');
            $presensiSummary = $this->attendanceEligibilityService->summarizeForKrsDetail($detail);

            return [
                'id_krs_detail' => $detail->id,
                'mahasiswa' => [
                    'id' => $detail->krs?->mahasiswa?->id,
                    'nim' => $detail->krs?->mahasiswa?->nim,
                    'nama_mahasiswa' => $detail->krs?->mahasiswa?->nama_mahasiswa,
                ],
                'nilai_akhir_existing' => [
                    'nilai_akhir' => $detail->nilai_akhir,
                    'nilai_huruf' => $detail->nilai_huruf,
                    'bobot_nilai' => $detail->bobot_nilai,
                    'status' => $detail->status,
                ],
                'presensi_summary' => $presensiSummary,
                'komponen' => $kelas->komponenPenilaian->map(function ($komponen) use ($nilaiMap) {
                    $nilai = $nilaiMap->get($komponen->id);

                    return [
                        'id_komponen_penilaian' => $komponen->id,
                        'nama' => $komponen->nama,
                        'bobot' => $komponen->bobot,
                        'nilai' => $nilai?->nilai,
                        'catatan' => $nilai?->catatan,
                    ];
                })->values(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'kelas' => [
                    'id' => $kelas->id,
                    'nama_kelas' => $kelas->nama_kelas,
                ],
                'workflow_penilaian' => $this->formatWorkflow($workflow),
                'komponen' => $kelas->komponenPenilaian,
                'total_bobot_aktif' => round((float) $kelas->komponenPenilaian->where('is_active', true)->sum('bobot'), 2),
                'mahasiswa' => $rows,
            ],
        ]);
    }

    public function sync(Request $request, string $id_komponen_penilaian): JsonResponse
    {
        $komponen = KomponenPenilaian::find($id_komponen_penilaian);
        if (!$komponen) {
            return response()->json([
                'success' => false,
                'message' => 'Komponen penilaian tidak ditemukan',
            ], 404);
        }

        $kelas = KelasKuliah::with('dosen_pengajar')->find($komponen->id_kelas_kuliah);
        if (!$kelas) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas kuliah tidak ditemukan',
            ], 404);
        }

        if ($authorizationResponse = $this->authorizeManageKelas($request, $kelas)) {
            return $authorizationResponse;
        }

        if ($workflowResponse = $this->ensureEditableWorkflow($kelas)) {
            return $workflowResponse;
        }

        $validated = $request->validate([
            'nilai' => 'required|array|min:1',
            'nilai.*.id_krs_detail' => 'required|uuid|exists:krs_detail,id',
            'nilai.*.nilai' => 'nullable|numeric|min:0|max:100',
            'nilai.*.catatan' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $komponen) {
            foreach ($validated['nilai'] as $item) {
                $detail = KRSDetail::where('id', $item['id_krs_detail'])
                    ->where('id_kelas_kuliah', $komponen->id_kelas_kuliah)
                    ->first();

                if (!$detail) {
                    continue;
                }

                NilaiKomponen::updateOrCreate(
                    [
                        'id_komponen_penilaian' => $komponen->id,
                        'id_krs_detail' => $detail->id,
                    ],
                    [
                        'nilai' => $item['nilai'] ?? null,
                        'catatan' => $item['catatan'] ?? null,
                    ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Nilai komponen berhasil disimpan',
        ]);
    }

    public function publishFinal(string $id_kelas_kuliah): JsonResponse
    {
        $kelas = KelasKuliah::with([
            'dosen_pengajar',
            'komponenPenilaian',
            'krsDetail.nilaiKomponen.komponenPenilaian',
        ])->find($id_kelas_kuliah);

        if (!$kelas) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas kuliah tidak ditemukan',
            ], 404);
        }

        if ($authorizationResponse = $this->authorizeManageKelas(request(), $kelas)) {
            return $authorizationResponse;
        }

        $workflow = $this->getOrCreatePenilaianKelas($kelas);
        if (!$workflow->canManageDraftData()) {
            return response()->json([
                'success' => false,
                'message' => 'Nilai akhir tidak dapat dipublikasikan karena penilaian kelas sudah terkunci',
                'data' => [
                    'workflow_penilaian' => $this->formatWorkflow($workflow),
                ],
            ], 422);
        }

        if ($kelas->komponenPenilaian->where('is_active', true)->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada komponen penilaian aktif untuk dipublikasikan',
            ], 422);
        }

        $totalBobotAktif = round((float) $kelas->komponenPenilaian->where('is_active', true)->sum('bobot'), 2);
        if (abs($totalBobotAktif - self::REQUIRED_TOTAL_BOBOT) >= 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'Total bobot komponen aktif harus tepat 100%',
            ], 422);
        }

        if ($kelas->krsDetail->where('status', KRSDetail::STATUS_TERDAFTAR)->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada peserta terdaftar yang dapat dipublikasikan nilainya',
            ], 422);
        }

        $ineligible = $this->attendanceEligibilityService->collectIneligibleForClass($kelas);
        if ($ineligible->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Masih ada mahasiswa yang belum memenuhi minimum presensi untuk publish nilai akhir',
                'data' => $ineligible,
            ], 422);
        }

        $result = [];

        DB::transaction(function () use ($kelas, &$result) {
            foreach ($kelas->krsDetail->where('status', KRSDetail::STATUS_TERDAFTAR) as $detail) {
                $final = $detail->syncFinalScoreFromKomponen();
                $result[] = [
                    'id_krs_detail' => $detail->id,
                    'nilai_akhir' => $final['nilai_akhir'],
                    'nilai_huruf' => $final['nilai_huruf'],
                    'bobot_nilai' => $final['bobot_nilai'],
                    'status' => $final['status'],
                ];
            }

            $this->getOrCreatePenilaianKelas($kelas)->markPublished(request()->user()?->id);
        });

        return response()->json([
            'success' => true,
            'message' => 'Nilai akhir berhasil dipublikasikan ke krs_detail',
            'data' => $result,
        ]);
    }

    public function setManualFinal(Request $request, string $id_krs_detail): JsonResponse
    {
        $detail = KRSDetail::with('kelasKuliah.dosen_pengajar')->find($id_krs_detail);

        if (!$detail) {
            return response()->json([
                'success' => false,
                'message' => 'Detail KRS tidak ditemukan',
            ], 404);
        }

        if ($authorizationResponse = $this->authorizeManageKelas($request, $detail->kelasKuliah)) {
            return $authorizationResponse;
        }

        if ($workflowResponse = $this->ensureEditableWorkflow($detail->kelasKuliah)) {
            return $workflowResponse;
        }

        $presensiSummary = $this->attendanceEligibilityService->summarizeForKrsDetail($detail);
        if (!$presensiSummary['is_layak_penilaian']) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa belum memenuhi minimum presensi untuk input nilai akhir manual',
                'data' => $presensiSummary,
            ], 422);
        }

        $validated = $request->validate([
            'nilai_akhir' => 'required|numeric|min:0|max:100',
            'catatan' => 'nullable|string',
        ]);

        $grading = KRSDetail::convertNumericScore((float) $validated['nilai_akhir']);

        DB::transaction(function () use ($detail, $validated, $grading) {
            $detail->inputNilai(
                round((float) $validated['nilai_akhir'], 2),
                $grading['nilai_huruf'],
                $grading['bobot_nilai']
            );

            if (!empty($validated['catatan'])) {
                $detail->update([
                    'catatan' => $validated['catatan'],
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Nilai akhir manual berhasil disimpan ke krs_detail',
            'data' => [
                'id_krs_detail' => $detail->id,
                'nilai_akhir' => round((float) $validated['nilai_akhir'], 2),
                'nilai_huruf' => $grading['nilai_huruf'],
                'bobot_nilai' => $grading['bobot_nilai'],
                'status' => $detail->fresh()->status,
            ],
        ]);
    }

    public function reopen(Request $request, string $id_kelas_kuliah): JsonResponse
    {
        $kelas = KelasKuliah::with('dosen_pengajar')->find($id_kelas_kuliah);
        if (!$kelas) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas kuliah tidak ditemukan',
            ], 404);
        }

        if ($authorizationResponse = $this->authorizeReopen($request, $kelas)) {
            return $authorizationResponse;
        }

        $validated = $request->validate([
            'reopen_reason' => 'required|string',
        ]);

        $workflow = $this->getOrCreatePenilaianKelas($kelas);
        if (!$workflow->canBeReopened()) {
            return response()->json([
                'success' => false,
                'message' => 'Penilaian kelas hanya dapat dibuka kembali setelah pernah dipublikasikan',
                'data' => [
                    'workflow_penilaian' => $this->formatWorkflow($workflow),
                ],
            ], 422);
        }

        $workflow->markReopened($request->user()?->id, $validated['reopen_reason']);

        return response()->json([
            'success' => true,
            'message' => 'Penilaian kelas berhasil dibuka kembali',
            'data' => $this->formatWorkflow($workflow->fresh()),
        ]);
    }

    private function getOrCreatePenilaianKelas(KelasKuliah $kelas): PenilaianKelas
    {
        $workflow = $kelas->penilaianKelas
            ?? PenilaianKelas::firstOrCreate(
                ['id_kelas_kuliah' => $kelas->id],
                ['status' => PenilaianKelas::STATUS_DRAFT]
            );

        $kelas->setRelation('penilaianKelas', $workflow);

        return $workflow;
    }

    private function ensureEditableWorkflow(KelasKuliah $kelas): ?JsonResponse
    {
        $workflow = $this->getOrCreatePenilaianKelas($kelas);

        if ($workflow->canManageDraftData()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Nilai komponen tidak dapat diubah karena penilaian kelas sudah dipublikasikan',
            'data' => [
                'workflow_penilaian' => $this->formatWorkflow($workflow),
            ],
        ], 422);
    }

    private function authorizeReopen(Request $request, KelasKuliah $kelas): ?JsonResponse
    {
        return $this->authorizeManageKelas($request, $kelas, 'membuka kembali penilaian kelas');
    }

    private function formatWorkflow(PenilaianKelas $workflow): array
    {
        return [
            'id' => $workflow->id,
            'id_kelas_kuliah' => $workflow->id_kelas_kuliah,
            'status' => $workflow->status,
            'validated_at' => $workflow->validated_at,
            'published_at' => $workflow->published_at,
            'published_by' => $workflow->published_by,
            'reopened_at' => $workflow->reopened_at,
            'reopened_by' => $workflow->reopened_by,
            'reopen_reason' => $workflow->reopen_reason,
            'can_manage_draft_data' => $workflow->canManageDraftData(),
            'can_reopen' => $workflow->canBeReopened(),
        ];
    }

    private function authorizeManageKelas(Request $request, KelasKuliah $kelas, string $actionLabel = 'mengelola penilaian kelas'): ?JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Silakan login ulang.',
            ], 401);
        }

        if ($user->hasRole('admin')) {
            return null;
        }

        $dosen = Dosen::query()
            ->where('user_id', $user->id)
            ->first();

        if (!$dosen) {
            return response()->json([
                'success' => false,
                'message' => "Hanya dosen pengampu atau admin yang dapat {$actionLabel}",
            ], 403);
        }

        $isPengampu = $kelas->dosen_pengajar
            ->contains(fn($pengampu) => $pengampu->id_registrasi_dosen === $dosen->id);

        if ($isPengampu) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Anda bukan dosen pengampu kelas ini',
        ], 403);
    }
}
