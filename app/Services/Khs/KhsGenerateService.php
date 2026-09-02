<?php

namespace App\Services\Khs;

use App\Models\Akademik\KHS;
use App\Models\Akademik\KHSDetail;
use App\Models\Akademik\KRS;
use App\Models\Akademik\KRSDetail;
use App\Models\Akademik\KhsImportBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class KhsGenerateService
{
    private ?bool $hasKeteranganColumn = null;

    public function __construct(
        private readonly KhsCalculationService $calculationService,
        private readonly KhsRevisionService $revisionService
    ) {
    }

    public function processBatch(array $validatedPayload, array $context = []): array
    {
        /** @var KhsImportBatch $batch */
        $batch = $context['batch'];
        $processedBy = $context['processed_by'] ?? null;
        $validRows = collect($validatedPayload['rows'] ?? [])->where('is_valid', true)->values();

        if ($validRows->isEmpty()) {
            $batch->update([
                'status' => 'failed',
                'processed_at' => now(),
                'summary' => array_merge($batch->summary ?? [], [
                    'process_message' => 'Tidak ada row valid untuk diproses.',
                ]),
            ]);

            return [
                'processed' => false,
                'message' => 'Tidak ada row valid untuk diproses.',
                'processed_count' => 0,
            ];
        }

        $processedKhsIds = [];
        $processedKrsDetailIds = [];
        $krsDetailSnapshots = [];

        try {
            DB::transaction(function () use (
                $validRows,
                $batch,
                $processedBy,
                &$processedKhsIds,
                &$processedKrsDetailIds,
                &$krsDetailSnapshots
            ) {
                foreach ($validRows as $row) {
                    $mahasiswaId = $row['mahasiswa']['id'];
                    $krs = $this->resolveSemesterKrs($mahasiswaId, $batch->id_semester);

                    $subjectMap = collect($row['subjects'] ?? [])
                        ->filter(function (array $subject) {
                            return (bool) ($subject['matched'] ?? false)
                                && !($subject['skipped'] ?? false)
                                && ($subject['nilai_akhir'] ?? null) !== null;
                        })
                        ->keyBy('id_krs_detail');

                    if ($subjectMap->isEmpty()) {
                        throw new RuntimeException('Tidak ada detail KRS valid yang dapat diproses untuk salah satu mahasiswa.');
                    }

                    $targetDetails = $krs->details
                        ->whereIn('id', $subjectMap->keys()->all())
                        ->values();

                    foreach ($targetDetails as $detail) {
                        if (!$detail instanceof KRSDetail) {
                            continue;
                        }

                        $krsDetailSnapshots[$detail->id] ??= $this->buildKrsDetailSnapshot($detail);
                        $subject = $subjectMap->get($detail->id);

                        $this->syncImportedScoreToKrsDetail($detail, $subject, $batch);
                        $processedKrsDetailIds[] = $detail->id;
                    }

                    $krs->refresh()->load([
                        'details.kelasKuliah.kurikulumMataKuliah.mataKuliah',
                    ]);

                    $khs = $this->generateKhsFromKrs(
                        $krs,
                        $batch,
                        $row,
                        $processedBy
                    );

                    $processedKhsIds[] = $khs->id;
                }
            });
        } catch (\Throwable $exception) {
            $batch->update([
                'status' => 'failed',
                'processed_at' => now(),
                'summary' => array_merge($batch->summary ?? [], [
                    'process_error' => $exception->getMessage(),
                ]),
            ]);

            return [
                'processed' => false,
                'message' => $exception->getMessage(),
                'processed_count' => 0,
            ];
        }

        $batch->update([
            'status' => 'processed',
            'total_success' => $validRows->count(),
            'total_failed' => count($validatedPayload['errors'] ?? []),
            'processed_at' => now(),
            'summary' => array_merge($batch->summary ?? [], [
                'processed_khs_ids' => array_values(array_unique($processedKhsIds)),
                'processed_krs_detail_ids' => array_values(array_unique($processedKrsDetailIds)),
                'krs_detail_snapshots' => array_values($krsDetailSnapshots),
                'processed_count' => $validRows->count(),
                'processed_by' => $processedBy,
                'process_mode' => 'sync_krs_detail_then_generate_khs',
            ]),
        ]);

        return [
            'processed' => true,
            'message' => 'Sinkronisasi nilai import ke KRS dan generate KHS berhasil diproses.',
            'processed_count' => $validRows->count(),
            'processed_khs_ids' => array_values(array_unique($processedKhsIds)),
            'processed_krs_detail_ids' => array_values(array_unique($processedKrsDetailIds)),
        ];
    }

    private function resolveSemesterKrs(string $mahasiswaId, string $semesterId): KRS
    {
        $krs = KRS::query()
            ->with([
                'details.kelasKuliah.kurikulumMataKuliah.mataKuliah',
            ])
            ->where('id_mahasiswa', $mahasiswaId)
            ->where('id_semester', $semesterId)
            ->first();

        if (!$krs) {
            throw new RuntimeException('KRS mahasiswa pada semester yang dipilih tidak ditemukan saat proses sinkronisasi.');
        }

        return $krs;
    }

    private function buildKrsDetailSnapshot(KRSDetail $detail): array
    {
        return [
            'id' => $detail->id,
            'id_krs' => $detail->id_krs,
            'id_kelas_kuliah' => $detail->id_kelas_kuliah,
            'id_mata_kuliah' => $detail->id_mata_kuliah,
            'id_import_batch' => $detail->id_import_batch,
            'status' => $detail->status,
            'catatan' => $detail->catatan,
            'nilai_akhir' => $detail->nilai_akhir,
            'nilai_huruf' => $detail->nilai_huruf,
            'bobot_nilai' => $detail->bobot_nilai,
            'mutu' => $detail->mutu,
        ];
    }

    private function syncImportedScoreToKrsDetail(KRSDetail $detail, array $subject, KhsImportBatch $batch): void
    {
        if ($detail->status === KRSDetail::STATUS_DROP) {
            throw new RuntimeException('Nilai import tidak dapat disinkronkan ke mata kuliah yang berstatus drop.');
        }

        if (($subject['nilai_akhir'] ?? null) === null || blank($subject['nilai_huruf'] ?? null)) {
            throw new RuntimeException('Nilai final import belum lengkap untuk salah satu mata kuliah yang diproses.');
        }

        $gradePoint = $subject['mutu'] !== null
            ? (float) $subject['mutu']
            : $this->resolveGradePointFromWeightedValue($subject, $detail);

        $detail->inputNilai(
            (float) $subject['nilai_akhir'],
            (string) $subject['nilai_huruf'],
            $gradePoint
        );

        $detail->update([
            'id_import_batch' => $batch->id,
        ]);
    }

    private function resolveGradePointFromWeightedValue(array $subject, KRSDetail $detail): float
    {
        $sks = (int) $detail->sks;
        $weightedValue = $subject['bobot_nilai'] ?? null;

        if ($weightedValue === null) {
            throw new RuntimeException('Nilai mutu/grade point import tidak lengkap untuk salah satu mata kuliah.');
        }

        if ($sks <= 0) {
            throw new RuntimeException('SKS mata kuliah tidak valid untuk sinkronisasi nilai import.');
        }

        return round(((float) $weightedValue) / $sks, 2);
    }

    private function generateKhsFromKrs(KRS $krs, KhsImportBatch $batch, array $row, ?string $processedBy = null): KHS
    {
        $khs = KHS::query()->firstOrCreate(
            [
                'id_mahasiswa' => $krs->id_mahasiswa,
                'id_semester' => $krs->id_semester,
            ],
            $this->withOptionalKeterangan([
                'total_sks_diambil' => 0,
                'total_sks_lulus' => 0,
                'ips' => 0,
                'ipk' => 0,
                'keterangan' => null,
                'is_final' => false,
                'updated_by' => $processedBy,
                'generated_at' => now(),
            ])
        );

        if ($khs->details()->exists()) {
            $this->revisionService->createSnapshot(
                $khs->load('details'),
                $batch->id,
                $processedBy,
                'Snapshot sebelum import ulang KHS'
            );
        }

        $khs->details()->delete();

        $countedDetails = $this->collectCountedKhsDetails($krs->details);
        if ($countedDetails->isEmpty()) {
            throw new RuntimeException('Tidak ada hasil studi final pada KRS yang dapat digenerate menjadi KHS.');
        }

        foreach ($countedDetails as $detail) {
            KHSDetail::create([
                'id_khs' => $khs->id,
                'id_krs_detail' => $detail->id,
                'id_kelas_kuliah' => $detail->id_kelas_kuliah,
                'id_mata_kuliah' => $detail->resolveMataKuliahId(),
                'id_import_batch' => $batch->id,
                'kode_mk' => $detail->kode_mata_kuliah,
                'nama_mk' => $detail->nama_mata_kuliah,
                'sks' => (int) $detail->sks,
                'nilai_akhir' => $detail->nilai_akhir,
                'nilai_huruf' => $detail->nilai_huruf,
                'bobot_nilai' => $detail->resolveWeightedBobotNilaiValue(),
                'mutu' => $detail->resolveMutuValue(),
                'status' => $detail->status,
            ]);
        }

        $summary = $this->calculationService->calculateSummaryFromKrsDetails($krs->details);

        $khs->refresh()->load(['details', 'mahasiswa']);
        $khs->update($this->withOptionalKeterangan([
            'total_sks_diambil' => $summary['total_sks_diambil'] ?? 0,
            'total_sks_lulus' => $summary['total_sks_lulus'] ?? 0,
            'ips' => $summary['ips'] ?? 0,
            'ipk' => $row['summary']['ipk'] ?? $this->calculationService->calculateIpkForKhs($khs),
            'keterangan' => $summary['keterangan'] ?? ($row['keterangan'] ?? null),
            'updated_by' => $processedBy,
            'generated_at' => now(),
        ]));

        return $khs->fresh(['details', 'mahasiswa']);
    }

    private function collectCountedKhsDetails(Collection $details): Collection
    {
        return $details
            ->filter(fn(KRSDetail $detail) => $detail->isCountedInKhs() && $detail->isFinalScored())
            ->values();
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
}
