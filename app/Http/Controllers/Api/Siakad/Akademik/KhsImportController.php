<?php

namespace App\Http\Controllers\Api\Siakad\Akademik;

use App\Exports\KhsImportResultExport;
use App\Exports\KhsTemplateExport;
use App\Exports\KhsImportErrorExport;
use App\Http\Controllers\Controller;
use App\Models\Akademik\KHS;
use App\Models\Akademik\KhsImportBatch;
use App\Models\Akademik\KhsImportError;
use App\Services\Khs\KhsExcelParserService;
use App\Services\Khs\KhsGenerateService;
use App\Services\Khs\KhsImportValidationService;
use App\Services\Khs\KhsManualUpdateService;
use App\Services\Khs\KhsRemarkService;
use App\Services\Khs\KhsRollbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

class KhsImportController extends Controller
{
    public function __construct(
        private readonly KhsExcelParserService $parserService,
        private readonly KhsImportValidationService $validationService,
        private readonly KhsGenerateService $generateService,
        private readonly KhsManualUpdateService $manualUpdateService,
        private readonly KhsRollbackService $rollbackService
    ) {
    }

    public function exportTemplate(Request $request)
    {
        $validated = $request->validate([
            'angkatan' => 'required|integer',
            'id_prodi' => 'required|uuid|exists:prodi,id',
            'id_semester' => 'required|uuid|exists:semester,id',
            'semester_ke' => 'required|integer|min:1',
        ]);

        $filename = 'template_nilai_khs_' . now()->format('Y_m_d_His') . '.xlsx';

        return Excel::download(new KhsTemplateExport($validated), $filename);
    }

    public function upload(): JsonResponse
    {
        $validated = request()->validate([
            'id_semester' => 'required|uuid|exists:semester,id',
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $file = request()->file('file');
        $storedPath = $file->store('khs-imports', 'local');
        try {
            $parsed = $this->parserService->parseFile($this->resolveBatchFilePath($storedPath));
        } catch (RuntimeException $exception) {
            Storage::disk('local')->delete($storedPath);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $batch = KhsImportBatch::create([
            'id_semester' => $validated['id_semester'],
            'uploaded_by' => request()->user()->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $storedPath,
            'status' => 'uploaded',
            'total_rows' => count($parsed['rows']),
            'summary' => [
                'parsed_subject_count' => count($parsed['subjects']),
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File import KHS berhasil diunggah.',
            'data' => [
                'batch' => $batch->fresh(),
                'parsed_subject_count' => count($parsed['subjects']),
                'total_rows' => count($parsed['rows']),
            ],
        ], 201);
    }

    public function preview(string $batchId): JsonResponse
    {
        $batch = KhsImportBatch::query()
            ->with('semester.tahunAkademik')
            ->find($batchId);

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch import KHS tidak ditemukan.',
            ], 404);
        }

        try {
            $parsed = $this->parserService->parseFile($this->resolveBatchFilePath($batch->file_path));
        } catch (RuntimeException $exception) {
            $batch->update([
                'status' => 'failed',
                'summary' => array_merge($batch->summary ?? [], [
                    'preview_error' => $exception->getMessage(),
                ]),
            ]);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $preview = $this->validationService->validateParsedPayload($parsed, [
            'id_semester' => $batch->id_semester,
        ]);

        KhsImportError::query()
            ->where('id_import_batch', $batch->id)
            ->delete();

        $errorRows = array_merge($preview['errors'], $preview['warnings']);
        foreach ($errorRows as $error) {
            KhsImportError::create([
                'id_import_batch' => $batch->id,
                'row_number' => $error['row_number'] ?? null,
                'nim' => $error['nim'] ?? null,
                'kode_mk' => $error['payload']['subjects'][0]['kode_mk'] ?? null,
                'error_type' => $error['error_type'],
                'message' => $error['message'],
                'payload' => $error['payload'] ?? null,
            ]);
        }

        $currentStatus = (string) ($batch->status ?? 'uploaded');
        $summary = array_merge($batch->summary ?? [], $preview['summary']);
        $batch->update([
            'status' => $currentStatus === 'processed' ? 'processed' : 'previewed',
            'total_rows' => $preview['summary']['total_rows'],
            'total_success' => $preview['summary']['total_valid'],
            'total_failed' => $preview['summary']['total_error'],
            'summary' => $summary,
        ]);

        $batch = $this->appendProcessedKhsItemsToBatch($batch->fresh(['errors', 'semester.tahunAkademik']));

        return response()->json([
            'success' => true,
            'message' => 'Preview import KHS berhasil dibuat.',
            'data' => [
                'batch' => $batch,
                'metadata' => $parsed['metadata'],
                'subjects' => $parsed['subjects'],
                'preview' => $preview,
            ],
        ]);
    }

    public function history(): JsonResponse
    {
        $batches = KhsImportBatch::query()
            ->with(['semester.tahunAkademik', 'uploader:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $batches,
        ]);
    }

    public function show(string $batchId): JsonResponse
    {
        $batch = KhsImportBatch::query()
            ->with([
                'semester.tahunAkademik',
                'uploader:id,name',
                'errors',
                'revisions.creator:id,name',
            ])
            ->find($batchId);

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch import KHS tidak ditemukan.',
            ], 404);
        }

        $batch = $this->appendProcessedKhsItemsToBatch($batch);

        return response()->json([
            'success' => true,
            'data' => $batch,
        ]);
    }

    public function process(string $batchId): JsonResponse
    {
        $batch = KhsImportBatch::query()
            ->with('semester.tahunAkademik')
            ->find($batchId);

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch import KHS tidak ditemukan.',
            ], 404);
        }

        try {
            $parsed = $this->parserService->parseFile($this->resolveBatchFilePath($batch->file_path));
        } catch (RuntimeException $exception) {
            $batch->update([
                'status' => 'failed',
                'processed_at' => now(),
                'summary' => array_merge($batch->summary ?? [], [
                    'process_error' => $exception->getMessage(),
                ]),
            ]);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $preview = $this->validationService->validateParsedPayload($parsed, [
            'id_semester' => $batch->id_semester,
        ]);

        $result = $this->generateService->processBatch($preview, [
            'batch' => $batch,
            'processed_by' => request()->user()?->id,
        ]);

        $statusCode = $result['processed'] ? 200 : 422;

        return response()->json([
            'success' => $result['processed'],
            'message' => $result['message'],
            'data' => [
                'batch' => $this->appendProcessedKhsItemsToBatch($batch->fresh(['errors', 'revisions', 'semester.tahunAkademik'])),
                'result' => $result,
            ],
        ], $statusCode);
    }

    public function rollback(string $batchId): JsonResponse
    {
        $result = $this->rollbackService->rollback($batchId, request()->user()?->id);
        $statusCode = $result['rolled_back'] ? 200 : 422;

        return response()->json([
            'success' => $result['rolled_back'],
            'message' => $result['message'],
            'data' => $result,
        ], $statusCode);
    }

    public function finalizeBatch(Request $request, string $batchId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $batch = KhsImportBatch::query()
            ->with('details')
            ->find($batchId);

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch import KHS tidak ditemukan.',
            ], 404);
        }

        if (($batch->status ?? '') !== 'processed') {
            return response()->json([
                'success' => false,
                'message' => 'Finalisasi semua KHS hanya bisa dilakukan untuk batch yang sudah diproses.',
            ], 422);
        }

        $result = $this->manualUpdateService->finalizeBatch(
            $batch,
            $request->user()?->id,
            $validated['reason'] ?? null
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => [
                'batch' => $this->appendProcessedKhsItemsToBatch($batch->fresh(['errors', 'revisions', 'semester.tahunAkademik'])),
                'result' => $result['data'],
            ],
        ], $result['success'] ? 200 : 422);
    }

    public function exportErrors(string $batchId)
    {
        $batch = KhsImportBatch::query()
            ->with('errors')
            ->find($batchId);

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch import KHS tidak ditemukan.',
            ], 404);
        }

        $filename = 'khs_import_errors_' . $batch->id . '.xlsx';

        return Excel::download(new KhsImportErrorExport($batch), $filename);
    }

    public function exportResults(string $batchId)
    {
        $batch = KhsImportBatch::query()->find($batchId);

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch import KHS tidak ditemukan.',
            ], 404);
        }

        try {
            $parsed = $this->parserService->parseFile($this->resolveBatchFilePath($batch->file_path));
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $preview = $this->validationService->validateParsedPayload($parsed, [
            'id_semester' => $batch->id_semester,
        ]);

        $filename = 'khs_import_results_' . $batch->id . '.xlsx';

        return Excel::download(new KhsImportResultExport($preview), $filename);
    }

    public function remarkReference(Request $request, KhsRemarkService $remarkService): JsonResponse
    {
        $validated = $request->validate([
            'ips' => 'required|numeric|min:0|max:4',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'ips' => (float) $validated['ips'],
                'keterangan' => $remarkService->resolveFromIps((float) $validated['ips']),
            ],
        ]);
    }

    private function resolveBatchFilePath(string $storedPath): string
    {
        if ($storedPath === '') {
            throw new RuntimeException('File import KHS tidak ditemukan karena path file kosong.');
        }

        if ($this->isAbsolutePath($storedPath) && is_file($storedPath)) {
            return $storedPath;
        }

        $candidatePaths = [];
        $checkedDisks = array_unique(array_filter([
            'local',
            Config::get('filesystems.default'),
            'public',
        ]));

        foreach ($checkedDisks as $diskName) {
            $disk = Storage::disk($diskName);

            if ($disk->exists($storedPath)) {
                return $disk->path($storedPath);
            }

            $candidatePaths[] = $disk->path($storedPath);
        }

        $legacyPaths = [
            storage_path('app/' . ltrim($storedPath, '/\\')),
            storage_path('app/private/' . ltrim($storedPath, '/\\')),
            storage_path('app/public/' . ltrim($storedPath, '/\\')),
        ];

        foreach ($legacyPaths as $legacyPath) {
            if (is_file($legacyPath)) {
                return $legacyPath;
            }

            $candidatePaths[] = $legacyPath;
        }

        throw new RuntimeException(
            'File import untuk batch ini tidak ditemukan di storage. Silakan upload ulang file Excel batch tersebut.'
        );
    }

    private function appendProcessedKhsItemsToBatch(KhsImportBatch $batch): KhsImportBatch
    {
        $summary = $batch->summary ?? [];
        $processedKhsIds = collect($summary['processed_khs_ids'] ?? [])
            ->filter()
            ->values();

        if ($processedKhsIds->isEmpty()) {
            return $batch;
        }

        $items = KHS::query()
            ->with('mahasiswa:id,nim,nama_mahasiswa')
            ->whereIn('id', $processedKhsIds)
            ->get()
            ->sortBy(function (KHS $khs) use ($processedKhsIds) {
                $index = $processedKhsIds->search($khs->id);

                return $index === false ? PHP_INT_MAX : $index;
            })
            ->values()
            ->map(function (KHS $khs) {
                return [
                    'id' => $khs->id,
                    'nim' => $khs->mahasiswa?->nim,
                    'nama_mahasiswa' => $khs->mahasiswa?->nama_mahasiswa,
                    'ips' => $khs->ips,
                    'ipk' => $khs->ipk,
                    'is_final' => (bool) $khs->is_final,
                ];
            })
            ->all();

        $summary['processed_khs_items'] = $items;
        $batch->summary = $summary;

        return $batch;
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^(?:[A-Za-z]:\\\\|\/)/', $path) === 1;
    }
}
