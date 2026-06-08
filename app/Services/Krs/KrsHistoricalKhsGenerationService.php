<?php

namespace App\Services\Krs;

use App\Models\Akademik\KHS;
use App\Models\Akademik\KHSDetail;
use App\Models\Akademik\KRS;
use App\Models\Akademik\KRSDetail;
use App\Models\Akademik\KrsCollectiveBatch;
use App\Models\Akademik\KrsCollectiveBatchItem;
use App\Models\MasterData\Mahasiswa;
use App\Services\Khs\KhsCalculationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KrsHistoricalKhsGenerationService
{
    private ?bool $hasKeteranganColumn = null;

    public function __construct(
        private readonly KhsCalculationService $calculationService
    ) {
    }

    public function preview(array $payload): Collection
    {
        $studentPayloads = collect($payload['students_payload'] ?? [])->keyBy('id_mahasiswa');

        return $this->loadHistoricalKrs($payload)->map(function (array $item) use ($payload, $studentPayloads) {
            $krs = $item['krs'];

            if (!$krs) {
                return $this->result($item['mahasiswa'], KrsCollectiveBatchItem::STATUS_FAILED, 'KRS historis tidak ditemukan');
            }

            if ($krs->status_approval !== KRS::STATUS_APPROVED || !$krs->is_locked) {
                return $this->result($item['mahasiswa'], KrsCollectiveBatchItem::STATUS_SKIPPED, 'KRS historis belum final sehingga belum bisa digenerate menjadi KHS', [
                    'id_krs' => $krs->id,
                ]);
            }

            if ($validationError = $this->validateKrsForKhs($krs)) {
                return $this->result($item['mahasiswa'], KrsCollectiveBatchItem::STATUS_SKIPPED, $validationError, [
                    'id_krs' => $krs->id,
                ]);
            }

            $existingKhs = KHS::query()
                ->where('id_mahasiswa', $item['mahasiswa']['id'])
                ->where('id_semester', $payload['id_semester'])
                ->first();

            if ($existingKhs?->is_final) {
                return $this->result($item['mahasiswa'], KrsCollectiveBatchItem::STATUS_SKIPPED, 'KHS final sudah ada dan tidak dapat digenerate ulang', [
                    'id_krs' => $krs->id,
                    'id_khs' => $existingKhs->id,
                ]);
            }

            $studentPayload = $studentPayloads->get($item['mahasiswa']['id']);
            $snapshot = $this->buildSemesterSnapshot($item['mahasiswa']['id'], $payload['id_semester'], $krs, $studentPayload);

            if ($snapshot['requires_manual_ipk'] && $snapshot['summary']['ipk'] === null) {
                return $this->result($item['mahasiswa'], KrsCollectiveBatchItem::STATUS_FAILED, 'IPK manual wajib diisi untuk generate KHS historis semester di atas semester 1', [
                    'id_krs' => $krs->id,
                    'existing_khs_id' => $existingKhs?->id,
                    'semester_ke' => $snapshot['semester_ke'],
                    'requires_manual_ipk' => true,
                ]);
            }

            return $this->result($item['mahasiswa'], KrsCollectiveBatchItem::STATUS_READY, 'Hasil studi siap dibuat dari KRS historis yang sudah disimpan', [
                'id_krs' => $krs->id,
                'existing_khs_id' => $existingKhs?->id,
                'semester_ke' => $snapshot['semester_ke'],
                'requires_manual_ipk' => $snapshot['requires_manual_ipk'],
                'summary' => $snapshot['summary'],
            ]);
        })->values();
    }

    public function execute(array $payload, array $selectedMahasiswaIds): Collection
    {
        $studentPayloads = collect($payload['students_payload'] ?? [])->keyBy('id_mahasiswa');
        $preview = $this->preview([
            'id_semester' => $payload['id_semester'],
            'selected_mahasiswa_ids' => $selectedMahasiswaIds,
            'students_payload' => $studentPayloads->values()->all(),
        ])->keyBy('id_mahasiswa');

        $krsMap = $this->loadHistoricalKrs([
            'id_semester' => $payload['id_semester'],
            'selected_mahasiswa_ids' => $selectedMahasiswaIds,
        ])->keyBy(fn(array $item) => $item['mahasiswa']['id']);

        return collect($selectedMahasiswaIds)->map(function (string $studentId) use ($preview, $krsMap, $payload, $studentPayloads) {
            $previewItem = $preview->get($studentId);
            $item = $krsMap->get($studentId);

            if (!$previewItem || $previewItem['status'] !== KrsCollectiveBatchItem::STATUS_READY || !$item) {
                return $previewItem ?? [
                    'id_mahasiswa' => $studentId,
                    'status' => KrsCollectiveBatchItem::STATUS_FAILED,
                    'message' => 'Mahasiswa tidak ditemukan pada saat generate KHS',
                    'meta' => [
                        'action_type' => KrsCollectiveBatch::ACTION_GENERATE_KHS,
                    ],
                ];
            }

            $krs = $item['krs'];

            try {
                $studentPayload = $studentPayloads->get($studentId);
                $snapshot = $this->buildSemesterSnapshot($studentId, $payload['id_semester'], $krs, $studentPayload);

                $khs = DB::transaction(function () use ($studentId, $payload, $snapshot) {
                    $khs = KHS::updateOrCreate(
                        [
                            'id_mahasiswa' => $studentId,
                            'id_semester' => $payload['id_semester'],
                        ],
                        $this->withOptionalKeterangan([
                            'total_sks_diambil' => $snapshot['summary']['total_sks_diambil'],
                            'total_sks_lulus' => $snapshot['summary']['total_sks_lulus'],
                            'ips' => $snapshot['summary']['ips'],
                            'ipk' => $snapshot['summary']['ipk'],
                            'keterangan' => $snapshot['summary']['keterangan'] ?? null,
                            'is_final' => false,
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

                    return $khs;
                });

                return $this->result($item['mahasiswa'], KrsCollectiveBatchItem::STATUS_EXECUTED, 'Hasil studi berhasil dibuat dari KRS historis', [
                        'action_type' => KrsCollectiveBatch::ACTION_GENERATE_KHS,
                        'id_krs' => $krs->id,
                        'id_khs' => $khs->id,
                        'semester_ke' => $snapshot['semester_ke'],
                        'requires_manual_ipk' => $snapshot['requires_manual_ipk'],
                        'summary' => $snapshot['summary'],
                    ]);
            } catch (\Throwable $exception) {
                return $this->result($item['mahasiswa'], KrsCollectiveBatchItem::STATUS_FAILED, $exception->getMessage(), [
                    'action_type' => KrsCollectiveBatch::ACTION_GENERATE_KHS,
                    'id_krs' => $krs->id,
                ]);
            }
        })->values();
    }

    private function loadHistoricalKrs(array $payload): Collection
    {
        $studentIds = collect($payload['selected_mahasiswa_ids'] ?? [])
            ->filter()
            ->values();

        $mahasiswa = Mahasiswa::query()
            ->whereIn('id', $studentIds->all())
            ->get()
            ->keyBy('id');

        $krsMap = KRS::query()
            ->with([
                'details.kelasKuliah.penilaianKelas',
                'details.kelasKuliah.kurikulumMataKuliah.mataKuliah',
                'semester.tahunAkademik',
            ])
            ->where('id_semester', $payload['id_semester'])
            ->whereIn('id_mahasiswa', $studentIds->all())
            ->get()
            ->keyBy('id_mahasiswa');

        return $studentIds->map(function (string $studentId) use ($mahasiswa, $krsMap) {
            $student = $mahasiswa->get($studentId);

            return [
                'mahasiswa' => [
                    'id' => $student?->id,
                    'nim' => $student?->nim,
                    'nama_mahasiswa' => $student?->nama_mahasiswa,
                ],
                'krs' => $krsMap->get($studentId),
            ];
        })->filter(fn(array $item) => !empty($item['mahasiswa']['id']));
    }

    private function buildSemesterSnapshot(string $mahasiswaId, string $semesterId, KRS $krs, ?array $studentPayload = null): array
    {
        $details = $krs->details
            ->filter(fn(KRSDetail $detail) => $detail->status !== KRSDetail::STATUS_DROP)
            ->map(function (KRSDetail $detail) {
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
            })
            ->values();

        $semesterKe = $this->resolveSemesterKe($krs, $details);
        $requiresManualIpk = $semesterKe > 1;
        $summary = $this->calculationService->calculateSummary(
            $details->map(function (array $detail) {
                return [
                    'sks' => (int) ($detail['sks'] ?? 0),
                    'mutu' => $detail['mutu'] !== null ? (float) $detail['mutu'] : null,
                    'bobot_nilai' => $detail['bobot_nilai'] !== null ? (float) $detail['bobot_nilai'] : null,
                    'status' => $detail['status'] ?? null,
                ];
            })
        );
        $ipk = $requiresManualIpk
            ? $this->resolveManualIpk($studentPayload)
            : $summary['ips'];
        $historicalRemark = $krs->details
            ->filter(fn(KRSDetail $detail) => $detail->status !== KRSDetail::STATUS_DROP)
            ->pluck('catatan')
            ->filter(fn($catatan) => filled($catatan))
            ->first();

        return [
            'semester_ke' => $semesterKe,
            'requires_manual_ipk' => $requiresManualIpk,
            'summary' => [
                'id_mahasiswa' => $mahasiswaId,
                'id_semester' => $semesterId,
                'total_sks_diambil' => $summary['total_sks_diambil'],
                'total_sks_lulus' => $summary['total_sks_lulus'],
                'ips' => $summary['ips'],
                'ipk' => $ipk,
                'keterangan' => $historicalRemark ?? $summary['keterangan'],
            ],
            'details' => $details,
        ];
    }

    private function resolveSemesterKe(KRS $krs, Collection $details): int
    {
        $semesterKe = $krs->details
            ->pluck('kelasKuliah.kurikulumMataKuliah.semester_ke')
            ->filter(fn($value) => $value !== null)
            ->map(fn($value) => (int) $value)
            ->values();

        if ($semesterKe->isNotEmpty()) {
            return (int) $semesterKe->max();
        }

        return $details->isEmpty() ? 0 : 1;
    }

    private function resolveManualIpk(?array $studentPayload): ?float
    {
        $ipk = $studentPayload['ipk'] ?? null;

        if ($ipk === null || $ipk === '') {
            return null;
        }

        return round((float) $ipk, 2);
    }

    private function validateKrsForKhs(KRS $krs): ?string
    {
        $details = $krs->details;

        $activeDetails = $details
            ->filter(fn(KRSDetail $detail) => $detail->status !== KRSDetail::STATUS_DROP)
            ->values();

        if ($activeDetails->isEmpty()) {
            return 'Belum ada mata kuliah aktif pada KRS historis ini untuk dibuatkan hasil studi';
        }

        $invalidScoreCount = $activeDetails->filter(fn(KRSDetail $detail) => !$detail->isFinalScored())->count();
        if ($invalidScoreCount > 0) {
            return 'Masih ada nilai historis pada KRS detail yang belum final atau belum lengkap';
        }

        return null;
    }

    private function result(array $student, string $status, string $message, array $meta = []): array
    {
        return [
            'id_mahasiswa' => $student['id'] ?? null,
            'nim' => $student['nim'] ?? null,
            'nama_mahasiswa' => $student['nama_mahasiswa'] ?? null,
            'status' => $status,
            'message' => $message,
            'meta' => array_merge([
                'action_type' => KrsCollectiveBatch::ACTION_GENERATE_KHS,
            ], $meta),
        ];
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
