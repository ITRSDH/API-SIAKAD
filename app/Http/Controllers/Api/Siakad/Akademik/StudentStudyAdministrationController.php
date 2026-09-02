<?php

namespace App\Http\Controllers\Api\Siakad\Akademik;

use App\Http\Controllers\Controller;
use App\Services\Akademik\StudentStudyAdministrationService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentStudyAdministrationController extends Controller
{
    public function __construct(
        private readonly StudentStudyAdministrationService $service
    ) {
    }

    public function filters(Request $request): JsonResponse
    {
        $this->authorizeWorkspaceAccess($request);

        return response()->json([
            'success' => true,
            'data' => $this->service->filters(),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorizeWorkspaceAccess($request);

        $validated = $request->validate([
            'id_semester' => 'nullable|uuid|exists:semester,id',
            'id_prodi' => 'nullable|uuid|exists:prodi,id',
            'angkatan' => 'nullable|integer|min:1900|max:2100',
            'semester_ke' => 'nullable|integer|min:1|max:14',
            'mode' => 'nullable|in:historis,aktif',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->summary($validated),
        ]);
    }

    public function batchHistory(Request $request): JsonResponse
    {
        $this->authorizeWorkspaceAccess($request);

        $validated = $request->validate([
            'id_semester' => 'nullable|uuid|exists:semester,id',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->batchHistory($validated),
        ]);
    }

    public function readyForKhs(Request $request): JsonResponse
    {
        $this->authorizeWorkspaceAccess($request);

        $validated = $request->validate([
            'id_semester' => 'required|uuid|exists:semester,id',
            'id_prodi' => 'nullable|uuid|exists:prodi,id',
            'angkatan' => 'nullable|integer|min:1900|max:2100',
            'q' => 'nullable|string|max:120',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->readyForKhs($validated),
        ]);
    }

    public function batchShow(Request $request, string $source, string $id): JsonResponse
    {
        $this->authorizeWorkspaceAccess($request);

        $batch = $this->service->findBatch($source, $id);

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch administrasi studi tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $batch,
        ]);
    }

    /**
     * Simpan nilai manual (grid masal) langsung ke krs_detail.
     * Khusus admin/BAAK; menulis seperti import (tanpa penilaian/komponen).
     */
    public function saveManualNilai(Request $request): JsonResponse
    {
        $this->authorizeAdminOnly($request);

        $validated = $request->validate([
            'id_semester' => 'required|uuid|exists:semester,id',
            'id_prodi' => 'required|uuid|exists:prodi,id',
            'angkatan' => 'nullable|integer|min:1900|max:2100',
            'semester_ke' => 'required|integer|min:1|max:14',
            'rows' => 'required|array|max:500',
            'rows.*.id_mahasiswa' => 'required|uuid|exists:mahasiswa,id',
            'rows.*.courses' => 'present|array',
            'rows.*.courses.*.id_kelas_kuliah' => 'required|uuid|exists:kelas_kuliah,id',
            'rows.*.courses.*.nilai_akhir' => 'nullable|numeric|min:0|max:100',
        ]);

        $results = $this->service->saveManualScores($validated);

        $successCount = collect($results)->where('status', 'success')->count();
        $failedCount = collect($results)->where('status', 'failed')->count();

        return response()->json([
            'success' => true,
            'message' => $failedCount
                ? "Nilai tersimpan untuk {$successCount} mahasiswa; {$failedCount} gagal."
                : "Nilai berhasil disimpan untuk {$successCount} mahasiswa.",
            'data' => [
                'results' => $results,
            ],
        ]);
    }

    /**
     * Konteks nilai existing untuk grid masal (Input Nilai — manual).
     * Admin-only.
     */
    public function manualNilaiContext(Request $request): JsonResponse
    {
        $this->authorizeAdminOnly($request);

        $validated = $request->validate([
            'id_semester' => 'required|uuid|exists:semester,id',
            'id_prodi' => 'nullable|uuid|exists:prodi,id',
            'angkatan' => 'nullable|integer|min:1900|max:2100',
            'semester_ke' => 'nullable|integer|min:1|max:14',
            'q' => 'nullable|string|max:120',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->manualNilaiContext($validated),
        ]);
    }

    private function authorizeAdminOnly(Request $request): void
    {
        $user = $request->user();

        if ($user && $user->hasRole('admin')) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Fitur input nilai manual hanya untuk admin/BAAK.',
        ], 403));
    }

    private function authorizeWorkspaceAccess(Request $request): void
    {
        $user = $request->user();

        if ($user && $user->hasRole('admin')) {
            return;
        }

        $permissions = [
            'akademik.krs-historical.filters',
            'akademik.krs-historical.batches',
            'akademik.khs.import.index',
            'akademik.khs.import.history',
            'akademik.khs.index',
        ];

        if ($user) {
            foreach ($permissions as $permission) {
                if ($user->can($permission)) {
                    return;
                }
            }
        }

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Anda tidak memiliki izin untuk mengakses workspace administrasi studi mahasiswa.',
        ], 403));
    }
}
