<?php

namespace App\Services\Khs;

use App\Models\Akademik\KHS;
use App\Models\Akademik\KHSDetail;
use App\Models\Akademik\KRS;
use App\Models\Akademik\KhsImportBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KhsManualUpdateService
{
    private ?bool $hasKeteranganColumn = null;

    public function __construct(
        private readonly GradeConversionService $gradeConversionService,
        private readonly KhsCalculationService $calculationService,
        private readonly KhsRevisionService $revisionService
    ) {
    }

    public function updateDetail(KHS $khs, KHSDetail $detail, array $payload, ?string $actorId = null): array
    {
        if ($detail->id_khs !== $khs->id) {
            return [
                'success' => false,
                'message' => 'Detail KHS tidak terkait dengan KHS yang dipilih.',
            ];
        }

        if ($khs->is_final && blank($payload['reason'] ?? null)) {
            return [
                'success' => false,
                'message' => 'Alasan revisi wajib diisi untuk mengubah KHS yang sudah final.',
            ];
        }

        $result = DB::transaction(function () use ($khs, $detail, $payload, $actorId) {
            $khs->loadMissing(['details', 'mahasiswa']);
            $this->revisionService->createSnapshot(
                $khs,
                null,
                $actorId,
                $payload['reason'] ?? 'Edit manual detail KHS'
            );

            [$nilaiAkhir, $nilaiHuruf, $bobotNilai, $mutu] = $this->resolveGradePayload($detail, $payload);

            $detail->update([
                'nilai_akhir' => $nilaiAkhir,
                'nilai_huruf' => $nilaiHuruf,
                'bobot_nilai' => $bobotNilai,
                'mutu' => $mutu,
                'status' => $mutu !== null && $mutu >= 2.00 ? 'lulus' : 'tidak_lulus',
            ]);

            $khs->refresh()->load(['details', 'mahasiswa']);
            $summary = $this->calculationService->calculateSummary(
                $khs->details->map(fn(KHSDetail $item) => [
                    'sks' => (int) $item->sks,
                    'mutu' => $item->mutu !== null ? (float) $item->mutu : null,
                    'bobot_nilai' => $item->bobot_nilai !== null ? (float) $item->bobot_nilai : null,
                    'status' => $item->status,
                ])
            );
            $semesterKe = $this->resolveSemesterKe($khs);

            $khs->update($this->withOptionalKeterangan([
                'total_sks_diambil' => $summary['total_sks_diambil'],
                'total_sks_lulus' => $summary['total_sks_lulus'],
                'ips' => $summary['ips'],
                'ipk' => $semesterKe <= 1
                    ? $summary['ips']
                    : $this->resolveManualIpk($khs, $payload),
                'keterangan' => $summary['keterangan'],
                'updated_by' => $actorId,
                'generated_at' => now(),
            ]));

            return $khs->fresh([
                'mahasiswa:id,nim,nama_mahasiswa',
                'semester.tahunAkademik:id,tahun_akademik',
                'details',
                'revisions.creator:id,name',
            ]);
        });

        return [
            'success' => true,
            'message' => 'Detail KHS berhasil diperbarui.',
            'data' => $result,
        ];
    }

    public function updateSummary(KHS $khs, array $payload, ?string $actorId = null): array
    {
        if ($khs->is_final && blank($payload['reason'] ?? null)) {
            return [
                'success' => false,
                'message' => 'Alasan revisi wajib diisi untuk mengubah KHS yang sudah final.',
            ];
        }

        $result = DB::transaction(function () use ($khs, $payload, $actorId) {
            $khs->loadMissing(['details', 'mahasiswa']);
            $this->revisionService->createSnapshot(
                $khs,
                null,
                $actorId,
                $payload['reason'] ?? 'Edit manual ringkasan KHS'
            );

            $summary = $this->calculationService->calculateSummary(
                $khs->details->map(fn(KHSDetail $item) => [
                    'sks' => (int) $item->sks,
                    'mutu' => $item->mutu !== null ? (float) $item->mutu : null,
                    'bobot_nilai' => $item->bobot_nilai !== null ? (float) $item->bobot_nilai : null,
                    'status' => $item->status,
                ])
            );
            $semesterKe = $this->resolveSemesterKe($khs);

            $khs->update($this->withOptionalKeterangan([
                'total_sks_diambil' => $summary['total_sks_diambil'],
                'total_sks_lulus' => $summary['total_sks_lulus'],
                'ips' => $summary['ips'],
                'ipk' => $semesterKe <= 1
                    ? $summary['ips']
                    : $this->resolveManualIpk($khs, $payload, true),
                'keterangan' => $summary['keterangan'],
                'updated_by' => $actorId,
                'generated_at' => now(),
            ]));

            return $khs->fresh([
                'mahasiswa:id,nim,nama_mahasiswa',
                'semester.tahunAkademik:id,tahun_akademik',
                'details',
                'revisions.creator:id,name',
            ]);
        });

        return [
            'success' => true,
            'message' => 'Ringkasan KHS berhasil diperbarui.',
            'data' => $result,
        ];
    }

    public function finalize(KHS $khs, ?string $actorId = null, ?string $reason = null): array
    {
        if ($khs->is_final) {
            return [
                'success' => true,
                'message' => 'KHS sudah dalam status final.',
                'data' => $khs->fresh([
                    'mahasiswa:id,nim,nama_mahasiswa',
                    'semester.tahunAkademik:id,tahun_akademik',
                    'details',
                ]),
            ];
        }

        $result = DB::transaction(function () use ($khs, $actorId, $reason) {
            $khs->loadMissing(['details', 'mahasiswa']);
            $this->revisionService->createSnapshot(
                $khs,
                null,
                $actorId,
                $reason ?? 'Finalisasi KHS'
            );

            $khs->update([
                'is_final' => true,
                'updated_by' => $actorId,
                'finalized_by' => $actorId,
                'finalized_at' => now(),
            ]);

            return $khs->fresh([
                'mahasiswa:id,nim,nama_mahasiswa',
                'semester.tahunAkademik:id,tahun_akademik',
                'details',
                'revisions.creator:id,name',
            ]);
        });

        return [
            'success' => true,
            'message' => 'KHS berhasil difinalisasi.',
            'data' => $result,
        ];
    }

    public function finalizeBatch(KhsImportBatch $batch, ?string $actorId = null, ?string $reason = null): array
    {
        $processedKhsIds = collect($batch->summary['processed_khs_ids'] ?? [])
            ->filter()
            ->values();

        if ($processedKhsIds->isEmpty()) {
            $processedKhsIds = $batch->details()
                ->whereNotNull('id_khs')
                ->pluck('id_khs')
                ->filter()
                ->unique()
                ->values();
        }

        if ($processedKhsIds->isEmpty()) {
            return [
                'success' => false,
                'message' => 'Batch ini belum memiliki KHS hasil proses yang bisa difinalisasi.',
                'data' => [
                    'finalized_count' => 0,
                    'already_final_count' => 0,
                    'khs_ids' => [],
                ],
            ];
        }

        $khsItems = KHS::query()
            ->with(['mahasiswa:id,nim,nama_mahasiswa', 'semester.tahunAkademik:id,tahun_akademik', 'details'])
            ->whereIn('id', $processedKhsIds)
            ->get();

        $alreadyFinalCount = $khsItems->where('is_final', true)->count();
        $finalizedCount = 0;

        DB::transaction(function () use ($khsItems, $actorId, $reason, &$finalizedCount) {
            foreach ($khsItems as $khs) {
                if ($khs->is_final) {
                    continue;
                }

                $result = $this->finalize($khs, $actorId, $reason ?? 'Finalisasi semua KHS dari batch import');
                if ($result['success']) {
                    $finalizedCount++;
                }
            }
        });

        return [
            'success' => true,
            'message' => $finalizedCount > 0
                ? 'Semua KHS yang belum final pada batch ini berhasil difinalisasi.'
                : 'Semua KHS pada batch ini sudah dalam status final.',
            'data' => [
                'finalized_count' => $finalizedCount,
                'already_final_count' => $alreadyFinalCount,
                'khs_ids' => $processedKhsIds->all(),
            ],
        ];
    }

    private function resolveGradePayload(KHSDetail $detail, array $payload): array
    {
        $nilaiAkhir = array_key_exists('nilai_akhir', $payload) ? $payload['nilai_akhir'] : $detail->nilai_akhir;
        $nilaiHuruf = array_key_exists('nilai_huruf', $payload) ? $this->normalizeString($payload['nilai_huruf']) : $detail->nilai_huruf;
        $bobotNilai = array_key_exists('bobot_nilai', $payload) ? $payload['bobot_nilai'] : $detail->bobot_nilai;
        $mutu = array_key_exists('mutu', $payload) ? $payload['mutu'] : $detail->mutu;

        if ($nilaiHuruf === null && $mutu === null && $nilaiAkhir !== null) {
            $fallback = $this->gradeConversionService->convertNumericScore((float) $nilaiAkhir);
            $nilaiHuruf = $fallback['nilai_huruf'];
            $mutu = $fallback['bobot_nilai'];
        } elseif ($nilaiHuruf !== null && $mutu === null) {
            $fallback = $this->gradeConversionService->convertLetterGrade($nilaiHuruf);
            if (!$fallback) {
                throw new \InvalidArgumentException('Nilai huruf tidak dikenali.');
            }

            $nilaiHuruf = $fallback['nilai_huruf'];
            $mutu = $fallback['bobot_nilai'];
        } elseif ($nilaiHuruf === null && $mutu !== null && $nilaiAkhir !== null) {
            $fallback = $this->gradeConversionService->convertNumericScore((float) $nilaiAkhir);
            $nilaiHuruf = $fallback['nilai_huruf'];
        }

        if ($bobotNilai === null && $mutu !== null && (int) $detail->sks > 0) {
            $bobotNilai = round(((int) $detail->sks) * (float) $mutu, 2);
        }

        return [
            $nilaiAkhir !== null ? round((float) $nilaiAkhir, 2) : null,
            $nilaiHuruf,
            $bobotNilai !== null ? round((float) $bobotNilai, 2) : null,
            $mutu !== null ? round((float) $mutu, 2) : null,
        ];
    }

    private function normalizeString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function resolveSemesterKe(KHS $khs): int
    {
        $krs = KRS::query()
            ->with('details.kelasKuliah.kurikulumMataKuliah')
            ->where('id_mahasiswa', $khs->id_mahasiswa)
            ->where('id_semester', $khs->id_semester)
            ->first();

        if (!$krs) {
            return 1;
        }

        $semesterKe = $krs->details
            ->pluck('kelasKuliah.kurikulumMataKuliah.semester_ke')
            ->filter(fn($value) => $value !== null)
            ->map(fn($value) => (int) $value)
            ->values();

        return $semesterKe->isNotEmpty() ? (int) $semesterKe->max() : 1;
    }

    private function resolveManualIpk(KHS $khs, array $payload, bool $requireExplicit = false): float
    {
        if (array_key_exists('ipk', $payload) && $payload['ipk'] !== null && $payload['ipk'] !== '') {
            return round((float) $payload['ipk'], 2);
        }

        if ($requireExplicit) {
            throw new \InvalidArgumentException('IPK manual wajib diisi untuk semester di atas semester 1.');
        }

        if ($khs->ipk !== null) {
            return round((float) $khs->ipk, 2);
        }

        throw new \InvalidArgumentException('IPK manual belum tersedia untuk KHS semester di atas semester 1.');
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
