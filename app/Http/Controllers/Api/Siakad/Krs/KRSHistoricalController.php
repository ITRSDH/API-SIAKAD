<?php

namespace App\Http\Controllers\Api\Siakad\Krs;

use App\Http\Controllers\Controller;
use App\Models\Akademik\KrsCollectiveBatch;
use App\Models\Akademik\KrsCollectiveBatchItem;
use App\Models\MasterData\Semester;
use App\Services\Krs\KrsHistoricalBatchLogService;
use App\Services\Krs\KrsHistoricalEligibilityService;
use App\Services\Krs\KrsHistoricalExecutionService;
use App\Services\Krs\KrsHistoricalKhsGenerationService;
use App\Services\Krs\KrsHistoricalPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class KRSHistoricalController extends Controller
{
    public function __construct(
        private readonly KrsHistoricalEligibilityService $eligibilityService,
        private readonly KrsHistoricalPreviewService $previewService,
        private readonly KrsHistoricalExecutionService $executionService,
        private readonly KrsHistoricalBatchLogService $batchLogService,
        private readonly KrsHistoricalKhsGenerationService $khsGenerationService
    ) {
    }

    public function filters(Request $request): JsonResponse
    {
        $this->authorizeHistoricalAccess($request, 'akademik.krs-historical.filters');

        return response()->json([
            'success' => true,
            'data' => $this->eligibilityService->filters(),
        ]);
    }

    public function eligibleMahasiswa(Request $request): JsonResponse
    {
        $this->authorizeHistoricalAccess($request, 'akademik.krs-historical.eligible-mahasiswa');

        $validated = $request->validate([
            'id_semester' => 'required|uuid|exists:semester,id',
            'id_prodi' => 'nullable|uuid|exists:prodi,id',
            'angkatan' => 'nullable|integer|min:1900|max:2100',
            'id_mahasiswa' => 'nullable|array',
            'id_mahasiswa.*' => 'uuid|exists:mahasiswa,id',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->eligibilityService->eligibleStudents($validated),
        ]);
    }

    public function packageClasses(Request $request): JsonResponse
    {
        $this->authorizeHistoricalAccess($request, 'akademik.krs-historical.package-classes');

        $validated = $request->validate([
            'id_semester' => 'required|uuid|exists:semester,id',
            'id_prodi' => 'required|uuid|exists:prodi,id',
            'semester_ke' => 'required|integer|min:1|max:14',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->eligibilityService->packageClasses($validated),
        ]);
    }

    public function repeatCandidates(Request $request): JsonResponse
    {
        $this->authorizeHistoricalAccess($request, 'akademik.krs-historical.package-classes');

        $validated = $request->validate([
            'id_semester' => 'required|uuid|exists:semester,id',
            'semester_ke' => 'required|integer|min:1|max:14',
            'id_mahasiswa' => 'required|array|min:1',
            'id_mahasiswa.*' => 'uuid|exists:mahasiswa,id',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->eligibilityService->repeatCandidates($validated),
        ]);
    }

    public function previewBuildHistoricalKrs(Request $request): JsonResponse
    {
        $this->authorizeHistoricalAccess($request, 'akademik.krs-historical.preview.build');
        $validated = $this->validateBuildPayload($request);

        return response()->json([
            'success' => true,
            'data' => $this->previewService->previewBuild($validated),
        ]);
    }

    public function executeBuildHistoricalKrs(Request $request): JsonResponse
    {
        $this->authorizeHistoricalAccess($request, 'akademik.krs-historical.execute.build');
        $validated = $this->validateBuildPayload($request, true);

        $results = $this->executionService->executeBuild($validated, $validated['selected_mahasiswa_ids']);
        $batch = $this->storeBatch($request, $validated, KrsCollectiveBatch::ACTION_BUILD_HISTORICAL_KRS, $results);

        return response()->json([
            'success' => true,
            'message' => 'Batch build KRS historis selesai diproses',
            'data' => [
                'batch_id' => $batch->id,
                'summary' => $batch->summary,
                'results' => $results,
            ],
        ]);
    }

    public function previewReopenHistoricalKrs(Request $request): JsonResponse
    {
        $this->authorizeHistoricalAccess($request, 'akademik.krs-historical.preview.reopen');
        $validated = $this->validateSelectionPayload($request);

        return response()->json([
            'success' => true,
            'data' => $this->previewService->previewReopen($validated),
        ]);
    }

    public function executeReopenHistoricalKrs(Request $request): JsonResponse
    {
        $this->authorizeHistoricalAccess($request, 'akademik.krs-historical.execute.reopen');
        $validated = $this->validateSelectionPayload($request, true);

        $results = $this->executionService->executeReopen($validated, $validated['selected_mahasiswa_ids']);
        $batch = $this->storeBatch($request, $validated, KrsCollectiveBatch::ACTION_REOPEN_HISTORICAL_KRS, $results);

        return response()->json([
            'success' => true,
            'message' => 'Batch buka ulang riwayat selesai diproses',
            'data' => [
                'batch_id' => $batch->id,
                'summary' => $batch->summary,
                'results' => $results,
            ],
        ]);
    }

    public function previewRefinalizeHistoricalKrs(Request $request): JsonResponse
    {
        $this->authorizeHistoricalAccess($request, 'akademik.krs-historical.preview.refinalize');
        $validated = $this->validateSelectionPayload($request);

        return response()->json([
            'success' => true,
            'data' => $this->previewService->previewRefinalize($validated),
        ]);
    }

    public function executeRefinalizeHistoricalKrs(Request $request): JsonResponse
    {
        $this->authorizeHistoricalAccess($request, 'akademik.krs-historical.execute.refinalize');
        $validated = $this->validateSelectionPayload($request, true);

        $results = $this->executionService->executeRefinalize($validated, $validated['selected_mahasiswa_ids']);
        $batch = $this->storeBatch($request, $validated, KrsCollectiveBatch::ACTION_REFINALIZE_HISTORICAL_KRS, $results);

        return response()->json([
            'success' => true,
            'message' => 'Batch finalisasi ulang riwayat selesai diproses',
            'data' => [
                'batch_id' => $batch->id,
                'summary' => $batch->summary,
                'results' => $results,
            ],
        ]);
    }

    public function previewResetHistoricalKrs(Request $request): JsonResponse
    {
        $this->authorizeHistoricalAccess($request, 'akademik.krs-historical.preview.reset');
        $validated = $this->validateSelectionPayload($request);

        return response()->json([
            'success' => true,
            'data' => $this->previewService->previewReset($validated),
        ]);
    }

    public function executeResetHistoricalKrs(Request $request): JsonResponse
    {
        $this->authorizeHistoricalAccess($request, 'akademik.krs-historical.execute.reset');
        $validated = $this->validateSelectionPayload($request, true);

        $results = $this->executionService->executeReset($validated, $validated['selected_mahasiswa_ids']);
        $batch = $this->storeBatch($request, $validated, KrsCollectiveBatch::ACTION_RESET_HISTORICAL_KRS, $results);

        return response()->json([
            'success' => true,
            'message' => 'Batch reset riwayat selesai diproses',
            'data' => [
                'batch_id' => $batch->id,
                'summary' => $batch->summary,
                'results' => $results,
            ],
        ]);
    }

    public function previewGenerateKhs(Request $request): JsonResponse
    {
        $this->authorizeHistoricalAccess($request, 'akademik.krs-historical.preview.generate-khs');
        $validated = $this->validateGenerateKhsPayload($request);

        return response()->json([
            'success' => true,
            'data' => $this->previewService->previewGenerateKhs($validated),
        ]);
    }

    public function executeGenerateKhs(Request $request): JsonResponse
    {
        $this->authorizeHistoricalAccess($request, 'akademik.krs-historical.execute.generate-khs');
        $validated = $this->validateGenerateKhsPayload($request, true);

        $results = $this->khsGenerationService->execute($validated, $validated['selected_mahasiswa_ids']);
        $batch = $this->storeBatch($request, $validated, KrsCollectiveBatch::ACTION_GENERATE_KHS, $results);

        return response()->json([
            'success' => true,
            'message' => 'Batch generate KHS historis selesai diproses',
            'data' => [
                'batch_id' => $batch->id,
                'summary' => $batch->summary,
                'results' => $results,
            ],
        ]);
    }

    public function batchHistory(Request $request): JsonResponse
    {
        $this->authorizeHistoricalAccess($request, 'akademik.krs-historical.batches');

        $validated = $request->validate([
            'id_semester' => 'nullable|uuid|exists:semester,id',
            'action_type' => [
                'nullable',
                Rule::in([
                    KrsCollectiveBatch::ACTION_BUILD_HISTORICAL_KRS,
                    KrsCollectiveBatch::ACTION_REOPEN_HISTORICAL_KRS,
                    KrsCollectiveBatch::ACTION_REFINALIZE_HISTORICAL_KRS,
                    KrsCollectiveBatch::ACTION_RESET_HISTORICAL_KRS,
                    KrsCollectiveBatch::ACTION_GENERATE_KHS,
                ]),
            ],
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $history = $this->batchLogService->history($validated, (int) ($validated['per_page'] ?? 15));

        return response()->json([
            'success' => true,
            'data' => $history->items(),
            'meta' => [
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
            ],
        ]);
    }

    public function batchShow(Request $request, string $id): JsonResponse
    {
        $this->authorizeHistoricalAccess($request, 'akademik.krs-historical.batches.show');

        $batch = $this->batchLogService->findBatch($id);
        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch historis tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $batch,
        ]);
    }

    private function validateSelectionPayload(Request $request, bool $requireSelectedIds = false): array
    {
        return $request->validate([
            'id_semester' => 'required|uuid|exists:semester,id',
            'selected_mahasiswa_ids' => [$requireSelectedIds ? 'required' : 'nullable', 'array'],
            'selected_mahasiswa_ids.*' => 'uuid|exists:mahasiswa,id',
            'notes' => 'nullable|string|max:2000',
        ]);
    }

    private function validateBuildPayload(Request $request, bool $requireSelectedIds = false): array
    {
        $validated = $request->validate([
            'id_semester' => 'required|uuid|exists:semester,id',
            'id_prodi' => 'required|uuid|exists:prodi,id',
            'angkatan' => 'nullable|integer|min:1900|max:2100',
            'semester_ke' => 'required|integer|min:1|max:14',
            'build_mode' => ['nullable', Rule::in(['krs_only', 'krs_with_scores'])],
            'selected_mahasiswa_ids' => 'required|array|min:1',
            'selected_mahasiswa_ids.*' => 'uuid|exists:mahasiswa,id',
            'notes' => 'nullable|string|max:2000',
            'students_payload' => 'required|array|min:1',
            'students_payload.*.id_mahasiswa' => 'required|uuid|exists:mahasiswa,id',
            'students_payload.*.courses' => 'required|array|min:1',
            'students_payload.*.courses.*.id_kelas_kuliah' => 'required|uuid|exists:kelas_kuliah,id',
            'students_payload.*.courses.*.nilai_akhir' => 'nullable|numeric|min:0|max:100',
            'students_payload.*.courses.*.catatan' => 'nullable|string|max:1000',
        ]);

        $buildMode = $validated['build_mode'] ?? 'krs_with_scores';
        if ($buildMode === 'krs_with_scores') {
            foreach ($validated['students_payload'] ?? [] as $studentIndex => $studentPayload) {
                foreach ($studentPayload['courses'] ?? [] as $courseIndex => $coursePayload) {
                    if (!array_key_exists('nilai_akhir', $coursePayload) || $coursePayload['nilai_akhir'] === null || $coursePayload['nilai_akhir'] === '') {
                        throw new HttpResponseException(response()->json([
                            'success' => false,
                            'message' => 'Payload build historis tidak valid',
                            'errors' => [
                                "students_payload.{$studentIndex}.courses.{$courseIndex}.nilai_akhir" => ['Nilai akhir wajib diisi jika mode build memakai nilai historis'],
                            ],
                        ], 422));
                    }
                }
            }
        }

        $selectedIds = collect($validated['selected_mahasiswa_ids'] ?? []);
        $studentPayload = collect($validated['students_payload'] ?? []);

        if ($selectedIds->duplicates()->isNotEmpty()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Payload build historis tidak valid',
                'errors' => [
                    'selected_mahasiswa_ids' => ['Setiap mahasiswa hanya boleh muncul satu kali dalam batch build historis'],
                ],
            ], 422));
        }

        if ($studentPayload->pluck('id_mahasiswa')->duplicates()->isNotEmpty()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Payload build historis tidak valid',
                'errors' => [
                    'students_payload' => ['Setiap mahasiswa hanya boleh muncul satu kali pada students_payload'],
                ],
            ], 422));
        }

        $studentPayloadById = $studentPayload->keyBy('id_mahasiswa');
        $missingStudents = $selectedIds->filter(fn(string $id) => !$studentPayloadById->has($id))->values();
        if ($missingStudents->isNotEmpty()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Payload build historis tidak valid',
                'errors' => [
                    'students_payload' => ['Setiap mahasiswa terpilih wajib memiliki detail kelas dan nilai historis'],
                ],
            ], 422));
        }

        foreach ($studentPayload as $index => $studentItem) {
            $courseIds = collect($studentItem['courses'] ?? [])->pluck('id_kelas_kuliah')->filter();
            if ($courseIds->duplicates()->isNotEmpty()) {
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'message' => 'Payload build historis tidak valid',
                    'errors' => [
                        "students_payload.{$index}.courses" => ['Satu mata kuliah historis tidak boleh dipilih dua kali untuk mahasiswa yang sama'],
                    ],
                ], 422));
            }
        }

        return $validated;
    }

    private function validateGenerateKhsPayload(Request $request, bool $requireSelectedIds = false): array
    {
        $validated = $request->validate([
            'id_semester' => 'required|uuid|exists:semester,id',
            'selected_mahasiswa_ids' => [$requireSelectedIds ? 'required' : 'nullable', 'array'],
            'selected_mahasiswa_ids.*' => 'uuid|exists:mahasiswa,id',
            'notes' => 'nullable|string|max:2000',
            'students_payload' => 'nullable|array',
            'students_payload.*.id_mahasiswa' => 'required|uuid|exists:mahasiswa,id',
            'students_payload.*.ipk' => 'nullable|numeric|min:0|max:4',
        ]);

        $selectedIds = collect($validated['selected_mahasiswa_ids'] ?? []);
        if ($selectedIds->duplicates()->isNotEmpty()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Payload generate KHS historis tidak valid',
                'errors' => [
                    'selected_mahasiswa_ids' => ['Setiap mahasiswa hanya boleh muncul satu kali dalam batch generate KHS historis'],
                ],
            ], 422));
        }

        $studentPayload = collect($validated['students_payload'] ?? []);
        if ($studentPayload->pluck('id_mahasiswa')->duplicates()->isNotEmpty()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Payload generate KHS historis tidak valid',
                'errors' => [
                    'students_payload' => ['Setiap mahasiswa hanya boleh muncul satu kali pada students_payload generate KHS'],
                ],
            ], 422));
        }

        return $validated;
    }

    private function authorizeHistoricalAccess(Request $request, string $permission): void
    {
        $user = $request->user();

        if ($user && ($user->hasRole('admin') || $user->can($permission))) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Anda tidak memiliki izin untuk menjalankan aksi riwayat studi historis ini',
            'permission' => $permission,
        ], 403));
    }

    private function storeBatch(Request $request, array $validated, string $actionType, Collection $results): KrsCollectiveBatch
    {
        $semester = Semester::query()->findOrFail($validated['id_semester']);
        $summary = $this->batchLogService->buildSummary($results);

        $batch = $this->batchLogService->createBatch([
            'created_by' => $request->user()->id,
            'id_tahun_akademik' => $semester->id_tahun_akademik,
            'id_semester' => $semester->id,
            'action_type' => $actionType,
            'filters' => [
                'id_semester' => $validated['id_semester'],
                'id_prodi' => $validated['id_prodi'] ?? null,
                'angkatan' => $validated['angkatan'] ?? null,
                'selected_mahasiswa_ids' => $validated['selected_mahasiswa_ids'] ?? [],
            ],
            'payload' => $validated,
            'summary' => $summary,
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->batchLogService->storeItems($batch, $results->map(function (array $result) {
            return [
                'id_mahasiswa' => $result['id_mahasiswa'],
                'id_krs' => $result['meta']['id_krs'] ?? null,
                'id_khs' => $result['meta']['id_khs'] ?? null,
                'status' => $result['status'] === KrsCollectiveBatchItem::STATUS_READY
                    ? KrsCollectiveBatchItem::STATUS_SKIPPED
                    : $result['status'],
                'message' => $result['message'],
                'meta' => $result['meta'] ?? null,
            ];
        })->all());

        return $batch->fresh(['creator:id,name', 'semester.tahunAkademik', 'items']);
    }
}
