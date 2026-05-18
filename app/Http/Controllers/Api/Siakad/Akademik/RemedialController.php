<?php

namespace App\Http\Controllers\Api\Siakad\Akademik;

use App\Http\Controllers\Controller;
use App\Models\Akademik\KRSDetail;
use App\Models\Akademik\Remedial;
use App\Services\AcademicPolicyService;
use App\Services\AttendanceEligibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RemedialController extends Controller
{
    public function __construct(
        private readonly AttendanceEligibilityService $attendanceEligibilityService,
        private readonly AcademicPolicyService $academicPolicyService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Remedial::with([
            'krsDetail.krs.mahasiswa',
            'kelasKuliah:id,nama_kelas',
        ])->orderByDesc('created_at');

        if ($request->filled('id_krs_detail')) {
            $query->where('id_krs_detail', $request->id_krs_detail);
        }

        if ($request->filled('id_kelas_kuliah')) {
            $query->where('id_kelas_kuliah', $request->id_kelas_kuliah);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $remedial = Remedial::with([
            'krsDetail.krs.mahasiswa',
            'kelasKuliah:id,nama_kelas',
        ])->find($id);

        if (!$remedial) {
            return response()->json([
                'success' => false,
                'message' => 'Data remedial tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $remedial,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_krs_detail' => 'required|uuid|exists:krs_detail,id',
            'tanggal_remedial' => 'nullable|date',
            'nilai_remedial' => 'required|numeric|min:0|max:100',
            'catatan' => 'nullable|string',
        ]);

        $detail = KRSDetail::with('kelasKuliah')->find($validated['id_krs_detail']);
        if (!$detail) {
            return response()->json([
                'success' => false,
                'message' => 'Detail KRS tidak ditemukan',
            ], 404);
        }

        $remedialPolicy = $this->academicPolicyService->get('remedial');
        $allowedStatuses = $remedialPolicy['allowed_krs_detail_statuses'] ?? [KRSDetail::STATUS_TIDAK_LULUS];
        if (!in_array($detail->status, $allowedStatuses, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Status hasil studi mahasiswa belum memenuhi syarat remedial',
                'data' => [
                    'status_detail' => $detail->status,
                    'allowed_statuses' => $allowedStatuses,
                ],
            ], 422);
        }

        $presensiSummary = $this->attendanceEligibilityService->summarizeForKrsDetail($detail);
        if (!$presensiSummary['is_layak_penilaian']) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa belum memenuhi minimum presensi untuk mengikuti remedial',
                'data' => $presensiSummary,
            ], 422);
        }

        $attemptKe = ((int) Remedial::where('id_krs_detail', $detail->id)->max('attempt_ke')) + 1;
        $maxAttempts = $remedialPolicy['max_attempts'] ?? null;
        if ($maxAttempts !== null && $attemptKe > (int) $maxAttempts) {
            return response()->json([
                'success' => false,
                'message' => 'Batas maksimum percobaan remedial sudah tercapai',
                'data' => [
                    'max_attempts' => (int) $maxAttempts,
                ],
            ], 422);
        }

        $grading = KRSDetail::convertNumericScore((float) $validated['nilai_remedial']);

        $remedial = Remedial::create([
            'id_krs_detail' => $detail->id,
            'id_kelas_kuliah' => $detail->id_kelas_kuliah,
            'attempt_ke' => $attemptKe,
            'tanggal_remedial' => $validated['tanggal_remedial'] ?? null,
            'nilai_sebelum' => $detail->nilai_akhir,
            'nilai_remedial' => $validated['nilai_remedial'],
            'nilai_final' => $validated['nilai_remedial'],
            'nilai_huruf_final' => $grading['nilai_huruf'],
            'bobot_nilai_final' => $grading['bobot_nilai'],
            'status' => 'draft',
            'catatan' => $validated['catatan'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data remedial berhasil ditambahkan',
            'data' => $remedial,
        ], 201);
    }

    public function publish(string $id): JsonResponse
    {
        $remedial = Remedial::with('krsDetail')->find($id);
        if (!$remedial) {
            return response()->json([
                'success' => false,
                'message' => 'Data remedial tidak ditemukan',
            ], 404);
        }

        if ($remedial->status === 'published') {
            return response()->json([
                'success' => false,
                'message' => 'Remedial sudah dipublikasikan sebelumnya',
            ], 422);
        }

        $presensiSummary = $this->attendanceEligibilityService->summarizeForKrsDetail($remedial->krsDetail);
        if (!$presensiSummary['is_layak_penilaian']) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa belum memenuhi minimum presensi untuk publish remedial',
                'data' => $presensiSummary,
            ], 422);
        }

        DB::transaction(function () use ($remedial) {
            $remedial->krsDetail->inputNilai(
                (float) $remedial->nilai_final,
                (string) $remedial->nilai_huruf_final,
                (float) $remedial->bobot_nilai_final
            );

            $remedial->update([
                'status' => 'published',
                'published_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Nilai remedial berhasil dipublikasikan ke krs_detail',
            'data' => $remedial->fresh(),
        ]);
    }

    public function cancel(string $id): JsonResponse
    {
        $remedial = Remedial::find($id);
        if (!$remedial) {
            return response()->json([
                'success' => false,
                'message' => 'Data remedial tidak ditemukan',
            ], 404);
        }

        if ($remedial->status === 'published') {
            return response()->json([
                'success' => false,
                'message' => 'Remedial yang sudah dipublikasikan tidak dapat dibatalkan lewat endpoint ini',
            ], 422);
        }

        $remedial->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data remedial berhasil dibatalkan',
            'data' => $remedial->fresh(),
        ]);
    }
}
