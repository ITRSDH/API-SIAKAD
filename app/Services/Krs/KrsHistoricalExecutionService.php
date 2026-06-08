<?php

namespace App\Services\Krs;

use App\Models\Akademik\KRS;
use App\Models\Akademik\KRSDetail;
use App\Models\Akademik\KrsCollectiveBatch;
use App\Models\Akademik\KrsCollectiveBatchItem;
use App\Models\MasterData\KelasKuliah;
use App\Models\MasterData\Mahasiswa;
use App\Services\Khs\KhsCalculationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KrsHistoricalExecutionService
{
    public function __construct(
        private readonly KrsHistoricalPreviewService $previewService,
        private readonly KhsCalculationService $calculationService
    ) {
    }

    public function executeBuild(array $payload, array $selectedMahasiswaIds): Collection
    {
        $buildMode = $payload['build_mode'] ?? 'krs_with_scores';
        $previewResults = $this->previewService->previewBuild($payload)->keyBy('id_mahasiswa');
        $students = Mahasiswa::query()->whereIn('id', $selectedMahasiswaIds)->get()->keyBy('id');

        return collect($selectedMahasiswaIds)->map(function (string $studentId) use ($previewResults, $payload, $students, $buildMode) {
            $preview = $previewResults->get($studentId);
            $student = $students->get($studentId);

            if (!$preview || $preview['status'] !== KrsCollectiveBatchItem::STATUS_READY || !$student) {
                return $preview ?? [
                    'id_mahasiswa' => $studentId,
                    'status' => KrsCollectiveBatchItem::STATUS_FAILED,
                    'message' => 'Mahasiswa tidak ditemukan pada saat eksekusi build historis',
                    'meta' => ['action_type' => KrsCollectiveBatch::ACTION_BUILD_HISTORICAL_KRS],
                ];
            }

            $resolvedCourses = collect($preview['meta']['courses'] ?? [])->values()->all();

            try {
                $krs = DB::transaction(function () use ($student, $payload, $resolvedCourses, $buildMode) {
                    $existingKrs = KRS::query()
                        ->where('id_mahasiswa', $student->id)
                        ->where('id_semester', $payload['id_semester'])
                        ->first();

                    if ($existingKrs) {
                        throw new \RuntimeException('KRS historis sudah terbentuk sebelum eksekusi dijalankan');
                    }

                    $krs = KRS::create([
                        'id_mahasiswa' => $student->id,
                        'id_semester' => $payload['id_semester'],
                        'tanggal_pengajuan' => now(),
                        'status_approval' => KRS::STATUS_REVISED,
                        'total_sks' => 0,
                        'is_locked' => false,
                        'catatan' => $payload['notes'] ?? null,
                    ]);

                    foreach ($resolvedCourses as $course) {
                        $kelasKuliah = KelasKuliah::query()
                            ->with('kurikulumMataKuliah.mataKuliah')
                            ->find($course['id_kelas_kuliah']);

                        if (!$kelasKuliah) {
                            throw new \RuntimeException('Kelas historis tidak ditemukan saat eksekusi');
                        }

                        $detail = KRSDetail::create([
                            'id_krs' => $krs->id,
                            'id_kelas_kuliah' => $course['id_kelas_kuliah'],
                            'id_mata_kuliah' => $kelasKuliah->kurikulumMataKuliah?->mataKuliah?->id,
                            'status' => KRSDetail::STATUS_TERDAFTAR,
                            'catatan' => $course['catatan'] ?? null,
                        ]);

                        if ($buildMode === 'krs_with_scores' && $course['nilai_akhir'] !== null && $course['nilai_huruf'] !== null && $course['mutu'] !== null) {
                            $detail->inputNilai(
                                (float) $course['nilai_akhir'],
                                (string) $course['nilai_huruf'],
                                (float) $course['mutu']
                            );
                        }
                    }

                    $krs->refresh()->load(['details.kelasKuliah.kurikulumMataKuliah.mataKuliah']);

                    $krs->update([
                        'total_sks' => $krs->calculateTotalSks(),
                    ]);

                    if ($buildMode === 'krs_with_scores') {
                        $summary = $this->calculationService->calculateSummaryFromKrsDetails($krs->details);
                        $historicalRemark = $summary['keterangan'] ?? null;

                        if (filled($historicalRemark)) {
                            $krs->details()
                                ->where('status', '!=', KRSDetail::STATUS_DROP)
                                ->update([
                                    'catatan' => $historicalRemark,
                                ]);
                        }

                        $krs->lock();
                    }

                    return $krs->fresh(['details.kelasKuliah.kurikulumMataKuliah.mataKuliah']);
                });

                return [
                    'id_mahasiswa' => $student->id,
                    'nim' => $student->nim,
                    'nama_mahasiswa' => $student->nama_mahasiswa,
                    'status' => KrsCollectiveBatchItem::STATUS_EXECUTED,
                    'message' => $buildMode === 'krs_only'
                        ? 'KRS historis berhasil didaftarkan tanpa nilai final'
                        : 'KRS historis berhasil dibentuk beserta nilai finalnya',
                    'meta' => [
                        'action_type' => KrsCollectiveBatch::ACTION_BUILD_HISTORICAL_KRS,
                        'build_mode' => $buildMode,
                        'id_krs' => $krs->id,
                        'total_sks' => $krs->total_sks,
                        'detail_count' => $krs->details->count(),
                        'status_approval' => $krs->status_approval,
                        'is_locked' => $krs->is_locked,
                    ],
                ];
            } catch (\Throwable $exception) {
                return [
                    'id_mahasiswa' => $student->id,
                    'nim' => $student->nim,
                    'nama_mahasiswa' => $student->nama_mahasiswa,
                    'status' => KrsCollectiveBatchItem::STATUS_FAILED,
                    'message' => $exception->getMessage(),
                    'meta' => [
                        'action_type' => KrsCollectiveBatch::ACTION_BUILD_HISTORICAL_KRS,
                    ],
                ];
            }
        })->values();
    }

    public function executeReopen(array $payload, array $selectedMahasiswaIds): Collection
    {
        return $this->executeMutation(
            $this->previewService->previewReopen($payload),
            $payload,
            $selectedMahasiswaIds,
            function (KRS $krs) {
                $krs->unlock();
                $krs->update([
                    'catatan' => $krs->catatan,
                ]);
            },
            KrsCollectiveBatch::ACTION_REOPEN_HISTORICAL_KRS,
            'KRS historis berhasil dibuka ulang'
        );
    }

    public function executeRefinalize(array $payload, array $selectedMahasiswaIds): Collection
    {
        return $this->executeMutation(
            $this->previewService->previewRefinalize($payload),
            $payload,
            $selectedMahasiswaIds,
            function (KRS $krs) {
                $krs->refresh()->load(['details.kelasKuliah.kurikulumMataKuliah.mataKuliah']);
                $summary = $this->calculationService->calculateSummaryFromKrsDetails($krs->details);
                $historicalRemark = $summary['keterangan'] ?? null;

                if (filled($historicalRemark)) {
                    $krs->details()
                        ->where('status', '!=', KRSDetail::STATUS_DROP)
                        ->update([
                            'catatan' => $historicalRemark,
                        ]);
                }

                $krs->update([
                    'total_sks' => $krs->calculateTotalSks(),
                ]);
                $krs->lock();
            },
            KrsCollectiveBatch::ACTION_REFINALIZE_HISTORICAL_KRS,
            'KRS historis berhasil difinalisasi ulang'
        );
    }

    public function executeReset(array $payload, array $selectedMahasiswaIds): Collection
    {
        return $this->executeMutation(
            $this->previewService->previewReset($payload),
            $payload,
            $selectedMahasiswaIds,
            function (KRS $krs) {
                $krs->details()->delete();
                $krs->update(['total_sks' => 0]);
            },
            KrsCollectiveBatch::ACTION_RESET_HISTORICAL_KRS,
            'Isi KRS historis berhasil direset'
        );
    }

    private function executeMutation(
        Collection $previewResults,
        array $payload,
        array $selectedMahasiswaIds,
        callable $operation,
        string $actionType,
        string $successMessage
    ): Collection {
        $previewByStudent = $previewResults->keyBy('id_mahasiswa');
        $students = Mahasiswa::query()->whereIn('id', $selectedMahasiswaIds)->get()->keyBy('id');
        $krsMap = KRS::query()
            ->where('id_semester', $payload['id_semester'])
            ->whereIn('id_mahasiswa', $selectedMahasiswaIds)
            ->get()
            ->keyBy('id_mahasiswa');

        return collect($selectedMahasiswaIds)->map(function (string $studentId) use ($previewByStudent, $students, $krsMap, $operation, $actionType, $successMessage) {
            $preview = $previewByStudent->get($studentId);
            $student = $students->get($studentId);
            $krs = $krsMap->get($studentId);

            if (!$preview || $preview['status'] !== KrsCollectiveBatchItem::STATUS_READY || !$student || !$krs) {
                return $preview ?? [
                    'id_mahasiswa' => $studentId,
                    'status' => KrsCollectiveBatchItem::STATUS_FAILED,
                    'message' => 'Data KRS historis tidak ditemukan pada saat eksekusi',
                    'meta' => ['action_type' => $actionType],
                ];
            }

            try {
                DB::transaction(fn() => $operation($krs));

                return [
                    'id_mahasiswa' => $student->id,
                    'nim' => $student->nim,
                    'nama_mahasiswa' => $student->nama_mahasiswa,
                    'status' => KrsCollectiveBatchItem::STATUS_EXECUTED,
                    'message' => $successMessage,
                    'meta' => [
                        'action_type' => $actionType,
                        'id_krs' => $krs->id,
                    ],
                ];
            } catch (\Throwable $exception) {
                return [
                    'id_mahasiswa' => $student->id,
                    'nim' => $student->nim,
                    'nama_mahasiswa' => $student->nama_mahasiswa,
                    'status' => KrsCollectiveBatchItem::STATUS_FAILED,
                    'message' => $exception->getMessage(),
                    'meta' => [
                        'action_type' => $actionType,
                        'id_krs' => $krs->id,
                    ],
                ];
            }
        })->values();
    }
}
