<?php

namespace App\Services\Akademik;

use App\Models\Akademik\KHS;
use App\Models\Akademik\KRS;
use App\Models\Akademik\KRSDetail;
use App\Models\Akademik\KhsImportBatch;
use App\Models\Akademik\KrsCollectiveBatch;
use App\Models\Akademik\KrsCollectiveBatchItem;
use App\Models\MasterData\Mahasiswa;
use App\Services\Krs\KrsHistoricalEligibilityService;
use Illuminate\Support\Collection;

class StudentStudyAdministrationService
{
    public function __construct(
        private readonly KrsHistoricalEligibilityService $historicalEligibilityService
    ) {
    }

    public function filters(): array
    {
        return $this->historicalEligibilityService->filters();
    }

    public function summary(array $filters): array
    {
        $studentQuery = Mahasiswa::query()
            ->where(function ($builder) {
                $builder->whereNull('status')
                    ->orWhere('status', '!=', 'nonaktif');
            });

        if (!empty($filters['id_prodi'])) {
            $studentQuery->where('id_prodi', $filters['id_prodi']);
        }

        if (!empty($filters['angkatan'])) {
            $studentQuery->where('angkatan', $filters['angkatan']);
        }

        $students = $studentQuery
            ->select(['id', 'id_prodi', 'angkatan'])
            ->orderBy('angkatan')
            ->get();

        $studentIds = $students->pluck('id')->all();

        if ($studentIds === []) {
            return [
                'summary_cards' => $this->emptySummaryCards(),
                'recent_batches' => [
                    'historical' => [],
                    'imports' => [],
                ],
            ];
        }

        $krsCollection = collect();
        $khsCollection = collect();

        if (!empty($filters['id_semester'])) {
            $krsCollection = KRS::query()
                ->with('details')
                ->where('id_semester', $filters['id_semester'])
                ->whereIn('id_mahasiswa', $studentIds)
                ->get();

            $khsCollection = KHS::query()
                ->where('id_semester', $filters['id_semester'])
                ->whereIn('id_mahasiswa', $studentIds)
                ->get();
        }

        $studentsWithKrs = $krsCollection->pluck('id_mahasiswa')->unique();
        $studentsReadyForKhs = $krsCollection
            ->filter(function (KRS $krs) {
                if (!$krs->is_locked || $krs->status_approval !== KRS::STATUS_APPROVED) {
                    return false;
                }

                $details = $krs->details ?? collect();
                if ($details->isEmpty()) {
                    return false;
                }

                return $details->every(fn($detail) => method_exists($detail, 'isFinalScored') && $detail->isFinalScored());
            })
            ->pluck('id_mahasiswa')
            ->unique();

        $studentsWithKhs = $khsCollection->pluck('id_mahasiswa')->unique();

        $recentHistoricalBatches = KrsCollectiveBatch::query()
            ->with(['creator:id,name', 'semester.tahunAkademik'])
            ->latest('executed_at')
            ->latest('created_at')
            ->limit(3)
            ->get();

        $recentImportBatches = KhsImportBatch::query()
            ->with(['uploader:id,name', 'semester.tahunAkademik'])
            ->latest('processed_at')
            ->latest('created_at')
            ->limit(3)
            ->get();

        return [
            'summary_cards' => [
                [
                    'key' => 'total_mahasiswa',
                    'label' => 'Mahasiswa Terfilter',
                    'value' => $students->count(),
                    'tone' => 'primary',
                    'description' => 'Mahasiswa sesuai konteks filter workspace.',
                ],
                [
                    'key' => 'belum_punya_krs',
                    'label' => 'Belum Punya KRS',
                    'value' => max($students->count() - $studentsWithKrs->count(), 0),
                    'tone' => 'warning',
                    'description' => 'Cocok untuk memulai dari tab KRS Kolektif.',
                ],
                [
                    'key' => 'siap_generate_khs',
                    'label' => 'Siap Generate KHS',
                    'value' => $studentsReadyForKhs->count(),
                    'tone' => 'success',
                    'description' => 'KRS approved dan nilai final sudah lengkap.',
                ],
                [
                    'key' => 'khs_sudah_terbentuk',
                    'label' => 'KHS Sudah Ada',
                    'value' => $studentsWithKhs->count(),
                    'tone' => 'info',
                    'description' => 'Mahasiswa yang sudah memiliki hasil studi pada semester ini.',
                ],
            ],
            'recent_batches' => [
                'historical' => $recentHistoricalBatches->values(),
                'imports' => $recentImportBatches->values(),
            ],
        ];
    }

    public function batchHistory(array $filters = []): array
    {
        $historicalBatches = KrsCollectiveBatch::query()
            ->with(['creator:id,name', 'semester.tahunAkademik'])
            ->when(!empty($filters['id_semester']), fn($query) => $query->where('id_semester', $filters['id_semester']))
            ->get()
            ->toBase()
            ->map(fn(KrsCollectiveBatch $batch) => $this->transformHistoricalBatch($batch));

        $importBatches = KhsImportBatch::query()
            ->with(['uploader:id,name', 'semester.tahunAkademik'])
            ->when(!empty($filters['id_semester']), fn($query) => $query->where('id_semester', $filters['id_semester']))
            ->get()
            ->toBase()
            ->map(fn(KhsImportBatch $batch) => $this->transformImportBatch($batch));

        return $historicalBatches
            ->merge($importBatches)
            ->sortByDesc(fn(array $batch) => $batch['executed_at_sort'] ?? '')
            ->values()
            ->all();
    }

    public function readyForKhs(array $filters = []): array
    {
        if (empty($filters['id_semester'])) {
            return [];
        }

        $studentQuery = Mahasiswa::query()
            ->with('prodi:id,nama_prodi')
            ->where(function ($builder) {
                $builder->whereNull('status')
                    ->orWhere('status', '!=', 'nonaktif');
            });

        if (!empty($filters['id_prodi'])) {
            $studentQuery->where('id_prodi', $filters['id_prodi']);
        }

        if (!empty($filters['angkatan'])) {
            $studentQuery->where('angkatan', $filters['angkatan']);
        }

        if (!empty($filters['q'])) {
            $search = trim($filters['q']);
            $studentQuery->where(function ($builder) use ($search) {
                $builder->where('nama_mahasiswa', 'like', '%' . $search . '%')
                    ->orWhere('nim', 'like', '%' . $search . '%');
            });
        }

        $students = $studentQuery
            ->orderBy('angkatan')
            ->orderBy('nim')
            ->get();

        $studentIds = $students->pluck('id')->all();

        $krsCollection = KRS::query()
            ->with([
                'details.kelasKuliah.penilaianKelas',
                'details.kelasKuliah.kurikulumMataKuliah.mataKuliah',
            ])
            ->where('id_semester', $filters['id_semester'])
            ->whereIn('id_mahasiswa', $studentIds)
            ->get()
            ->keyBy('id_mahasiswa');

        $khsCollection = KHS::query()
            ->where('id_semester', $filters['id_semester'])
            ->whereIn('id_mahasiswa', $studentIds)
            ->get()
            ->keyBy('id_mahasiswa');

        return $students->map(function (Mahasiswa $student) use ($krsCollection, $khsCollection) {
            /** @var KRS|null $krs */
            $krs = $krsCollection->get($student->id);
            $existingKhs = $khsCollection->get($student->id);

            if (!$krs) {
                return [
                    'id_mahasiswa' => $student->id,
                    'nim' => $student->nim,
                    'nama_mahasiswa' => $student->nama_mahasiswa,
                    'prodi' => $student->prodi?->nama_prodi,
                    'status' => 'not_ready',
                    'message' => 'KRS semester ini belum ditemukan.',
                    'existing_khs' => $existingKhs?->id,
                ];
            }

            if ($krs->status_approval !== KRS::STATUS_APPROVED || !$krs->is_locked) {
                return [
                    'id_mahasiswa' => $student->id,
                    'nim' => $student->nim,
                    'nama_mahasiswa' => $student->nama_mahasiswa,
                    'prodi' => $student->prodi?->nama_prodi,
                    'status' => 'not_ready',
                    'message' => 'KRS belum approved atau belum dikunci.',
                    'existing_khs' => $existingKhs?->id,
                ];
            }

            $details = $krs->details ?? collect();
            $pendingCount = $details->filter(fn($detail) => $detail->status === \App\Models\Akademik\KRSDetail::STATUS_TERDAFTAR)->count();
            $finalCount = $details->filter(fn($detail) => method_exists($detail, 'isFinalScored') && $detail->isFinalScored())->count();

            return [
                'id_mahasiswa' => $student->id,
                'nim' => $student->nim,
                'nama_mahasiswa' => $student->nama_mahasiswa,
                'prodi' => $student->prodi?->nama_prodi,
                'status' => $pendingCount === 0 && $finalCount > 0 ? 'ready' : 'not_ready',
                'message' => $pendingCount === 0 && $finalCount > 0
                    ? 'Siap dipreview dan digenerate.'
                    : 'Masih ada nilai atau hasil studi yang belum final.',
                'existing_khs' => $existingKhs?->id,
                'existing_khs_is_final' => (bool) ($existingKhs?->is_final ?? false),
                'total_detail' => $details->count(),
                'final_detail' => $finalCount,
                'pending_detail' => $pendingCount,
            ];
        })->values()->all();
    }

    public function findBatch(string $source, string $id): ?array
    {
        if ($source === 'historical') {
            $batch = KrsCollectiveBatch::query()
                ->with([
                    'creator:id,name',
                    'semester.tahunAkademik',
                    'items.mahasiswa:id,nim,nama_mahasiswa',
                ])
                ->find($id);

            return $batch ? $this->transformHistoricalBatchDetail($batch) : null;
        }

        if ($source === 'import') {
            $batch = KhsImportBatch::query()
                ->with([
                    'uploader:id,name',
                    'semester.tahunAkademik',
                    'errors',
                    'revisions.creator:id,name',
                ])
                ->find($id);

            return $batch ? $this->transformImportBatchDetail($batch) : null;
        }

        return null;
    }

    private function emptySummaryCards(): array
    {
        return [
            [
                'key' => 'total_mahasiswa',
                'label' => 'Mahasiswa Terfilter',
                'value' => 0,
                'tone' => 'primary',
                'description' => 'Mahasiswa sesuai konteks filter workspace.',
            ],
            [
                'key' => 'belum_punya_krs',
                'label' => 'Belum Punya KRS',
                'value' => 0,
                'tone' => 'warning',
                'description' => 'Cocok untuk memulai dari tab KRS Kolektif.',
            ],
            [
                'key' => 'siap_generate_khs',
                'label' => 'Siap Generate KHS',
                'value' => 0,
                'tone' => 'success',
                'description' => 'KRS approved dan nilai final sudah lengkap.',
            ],
            [
                'key' => 'khs_sudah_terbentuk',
                'label' => 'KHS Sudah Ada',
                'value' => 0,
                'tone' => 'info',
                'description' => 'Mahasiswa yang sudah memiliki hasil studi pada semester ini.',
            ],
        ];
    }

    private function transformHistoricalBatch(KrsCollectiveBatch $batch): array
    {
        $summary = $batch->summary ?? [];

        return [
            'source' => 'historical',
            'id' => $batch->id,
            'title' => $this->resolveHistoricalActionLabel($batch->action_type),
            'subtitle' => 'Riwayat Studi Historis',
            'status' => $this->resolveBatchStatusFromSummary($summary),
            'operator_name' => $batch->creator?->name,
            'semester' => $batch->semester,
            'action_type' => $batch->action_type,
            'summary' => [
                'total' => (int) ($summary['total'] ?? 0),
                'executed' => (int) ($summary['executed'] ?? 0),
                'skipped' => (int) ($summary['skipped'] ?? 0),
                'failed' => (int) ($summary['failed'] ?? 0),
            ],
            'executed_at' => $batch->executed_at ?? $batch->created_at,
            'executed_at_sort' => optional($batch->executed_at ?? $batch->created_at)?->toISOString(),
        ];
    }

    private function transformImportBatch(KhsImportBatch $batch): array
    {
        $summary = $batch->summary ?? [];

        return [
            'source' => 'import',
            'id' => $batch->id,
            'title' => $batch->file_name ?: 'Batch Import Nilai',
            'subtitle' => 'Import Nilai KHS',
            'status' => (string) ($batch->status ?? 'uploaded'),
            'operator_name' => $batch->uploader?->name,
            'semester' => $batch->semester,
            'action_type' => 'import_nilai_khs',
            'summary' => [
                'total' => (int) ($batch->total_rows ?? 0),
                'executed' => (int) ($batch->total_success ?? 0),
                'skipped' => (int) ($summary['total_warning'] ?? 0),
                'failed' => (int) ($batch->total_failed ?? 0),
            ],
            'executed_at' => $batch->processed_at ?? $batch->created_at,
            'executed_at_sort' => optional($batch->processed_at ?? $batch->created_at)?->toISOString(),
        ];
    }

    private function transformHistoricalBatchDetail(KrsCollectiveBatch $batch): array
    {
        $base = $this->transformHistoricalBatch($batch);

        $base['notes'] = $batch->notes;
        $base['filters'] = $batch->filters ?? [];
        $base['payload'] = $batch->payload ?? [];
        $base['items'] = $batch->items
            ->toBase()
            ->map(function (KrsCollectiveBatchItem $item) {
                return [
                    'id' => $item->id,
                    'status' => $item->status,
                    'message' => $item->message,
                    'mahasiswa' => [
                        'id' => $item->mahasiswa?->id,
                        'nim' => $item->mahasiswa?->nim,
                        'nama_mahasiswa' => $item->mahasiswa?->nama_mahasiswa,
                    ],
                    'meta' => $item->meta ?? [],
                ];
            })
            ->values()
            ->all();

        return $base;
    }

    private function transformImportBatchDetail(KhsImportBatch $batch): array
    {
        $base = $this->transformImportBatch($batch);

        $base['file_name'] = $batch->file_name;
        $base['notes'] = null;
        $base['filters'] = [
            'id_semester' => $batch->id_semester,
        ];
        $base['payload'] = [];
        $base['errors'] = $batch->errors->toBase()->map(function ($error) {
            return [
                'row_number' => $error->row_number,
                'nim' => $error->nim,
                'kode_mk' => $error->kode_mk,
                'error_type' => $error->error_type,
                'message' => $error->message,
            ];
        })->values()->all();
        $base['revisions'] = $batch->revisions->toBase()->map(function ($revision) {
            return [
                'revision_number' => $revision->revision_number,
                'reason' => $revision->reason,
                'creator_name' => $revision->creator?->name,
                'created_at' => $revision->created_at,
            ];
        })->values()->all();

        return $base;
    }

    private function resolveHistoricalActionLabel(?string $actionType): string
    {
        return match ($actionType) {
            KrsCollectiveBatch::ACTION_BUILD_HISTORICAL_KRS => 'Bentuk KRS Historis',
            KrsCollectiveBatch::ACTION_REOPEN_HISTORICAL_KRS => 'Buka Ulang Riwayat',
            KrsCollectiveBatch::ACTION_REFINALIZE_HISTORICAL_KRS => 'Finalisasi Ulang Riwayat',
            KrsCollectiveBatch::ACTION_RESET_HISTORICAL_KRS => 'Reset Isi Riwayat',
            KrsCollectiveBatch::ACTION_GENERATE_KHS => 'Generate KHS Historis',
            default => ucfirst(str_replace('_', ' ', (string) $actionType)),
        };
    }

    private function resolveBatchStatusFromSummary(array $summary): string
    {
        $executed = (int) ($summary['executed'] ?? 0);
        $failed = (int) ($summary['failed'] ?? 0);
        $skipped = (int) ($summary['skipped'] ?? 0);

        if ($executed > 0 && $failed === 0) {
            return 'processed';
        }

        if ($executed > 0 && $failed > 0) {
            return 'partial';
        }

        if ($failed > 0 && $executed === 0) {
            return 'failed';
        }

        if ($skipped > 0) {
            return 'previewed';
        }

        return 'uploaded';
    }

    /**
     * Menyimpan nilai akhir dari input manual (grid masal) langsung ke krs_detail.
     *
     * Meniru cara import nilai: menulis ke `KRSDetail` via `inputNilai` tanpa
     * menyentuh tabel nilai/komponen/penilaian. Khusus fitur "Input Nilai manual"
     * di halaman Input Nilai; hanya boleh dipakai oleh admin (guard di controller).
     *
     * @return array<int, array{id_mahasiswa: string, nim: string, nama_mahasiswa: string, status: string, message: string}>
     */
    public function saveManualScores(array $payload): array
    {
        $results = [];
        $studentRows = $payload['rows'] ?? [];

        foreach ($studentRows as $row) {
            $studentId = $row['id_mahasiswa'] ?? null;
            $courses = $row['courses'] ?? [];

            if (!$studentId) {
                $results[] = $this->manualResult($studentId, '', '', 'failed', 'ID mahasiswa tidak diberikan.');
                continue;
            }

            $student = Mahasiswa::query()->find($studentId);

            if (!$student) {
                $results[] = $this->manualResult($studentId, '', '', 'failed', 'Mahasiswa tidak ditemukan.');
                continue;
            }

            if (empty($courses)) {
                $results[] = $this->manualResult(
                    $studentId,
                    $student->nim,
                    $student->nama_mahasiswa,
                    'skipped',
                    'Tidak ada mata kuliah yang diisi untuk mahasiswa ini.'
                );
                continue;
            }

            $krs = KRS::query()
                ->with('details')
                ->where('id_mahasiswa', $studentId)
                ->where('id_semester', $payload['id_semester'] ?? null)
                ->first();

            if (!$krs) {
                $results[] = $this->manualResult(
                    $studentId,
                    $student->nim,
                    $student->nama_mahasiswa,
                    'failed',
                    'KRS semester ini belum ditemukan untuk mahasiswa.'
                );
                continue;
            }

            if ($krs->status_approval !== KRS::STATUS_APPROVED) {
                $results[] = $this->manualResult(
                    $studentId,
                    $student->nim,
                    $student->nama_mahasiswa,
                    'failed',
                    'KRS mahasiswa belum di-approve sehingga nilai tidak dapat diinput manual.'
                );
                continue;
            }

            $finalKhs = KHS::query()
                ->where('id_mahasiswa', $studentId)
                ->where('id_semester', $payload['id_semester'] ?? null)
                ->where('is_final', true)
                ->exists();

            if ($finalKhs) {
                $results[] = $this->manualResult(
                    $studentId,
                    $student->nim,
                    $student->nama_mahasiswa,
                    'failed',
                    'Mahasiswa sudah memiliki KHS final pada semester ini; nilai tidak dapat diubah via input manual.'
                );
                continue;
            }

            $detailByKelas = $krs->details->keyBy('id_kelas_kuliah');
            $saved = 0;
            $errors = [];

            foreach ($courses as $course) {
                $kelasId = $course['id_kelas_kuliah'] ?? null;
                $nilaiAkhir = $course['nilai_akhir'] ?? null;

                if (!$kelasId) {
                    $errors[] = 'Ada mata kuliah tanpa kelas.';
                    continue;
                }

                if ($nilaiAkhir === null || $nilaiAkhir === '' || !is_numeric($nilaiAkhir)) {
                    continue; // Kosong = memang tidak diisi.
                }

                $numericScore = (float) $nilaiAkhir;

                if ($numericScore < 0 || $numericScore > 100) {
                    $errors[] = 'Ada nilai di luar rentang 0–100.';
                    continue;
                }

                $detail = $detailByKelas->get($kelasId);

                if (!$detail) {
                    $errors[] = 'Mahasiswa tidak terdaftar pada salah satu kelas.';
                    continue;
                }

                if ($detail->status === KRSDetail::STATUS_DROP) {
                    $errors[] = 'Ada mata kuliah berstatus drop.';
                    continue;
                }

                $grading = KRSDetail::convertNumericScore($numericScore);
                $detail->inputNilai($numericScore, $grading['nilai_huruf'], $grading['bobot_nilai']);
                $saved++;
            }

            if ($saved === 0) {
                $message = $errors
                    ? implode(' ', array_unique($errors))
                    : 'Tidak ada nilai valid yang dapat disimpan untuk mahasiswa ini.';

                $results[] = $this->manualResult(
                    $studentId,
                    $student->nim,
                    $student->nama_mahasiswa,
                    'failed',
                    $message
                );
                continue;
            }

            $message = $errors
                ? "{$saved} nilai tersimpan. " . implode(' ', array_unique($errors))
                : "{$saved} nilai berhasil disimpan.";

            $results[] = $this->manualResult(
                $studentId,
                $student->nim,
                $student->nama_mahasiswa,
                'success',
                $message
            );
        }

        return $results;
    }

    private function manualResult(
        ?string $idMahasiswa,
        string $nim,
        string $namaMahasiswa,
        string $status,
        string $message
    ): array {
        return [
            'id_mahasiswa' => (string) $idMahasiswa,
            'nim' => $nim,
            'nama_mahasiswa' => $namaMahasiswa,
            'status' => $status,
            'message' => $message,
        ];
    }

    /**
     * Konteks nilai existing untuk grid masal halaman Input Nilai (Manual).
     *
     * Mengembalikan per mahasiswa: daftar nilai yang sudah tersimpan pada
     * KRS semester terpilih (id_kelas_kuliah → nilai_akhir). Admin-only.
     *
     * Hanya mahasiswa yang KRS-nya sudah di-approve pada semester terpilih yang
     * dikembalikan (sudah melakukan KRS), dan diberi penanda `has_final_khs`
     * agar UI bisa me-stabilo hijau mahasiswa yang KHS final-nya sudah ada.
     * Setiap kursus disertai `status` (semua status, termasuk terdaftar), `semester_ke`
     * dan `category` (paket/ulang/tambahan) untuk penanda visual di grid.
     *
     * @return array<int, array{
     *   id_mahasiswa: string, nim: string, nama_mahasiswa: string,
     *   prodi: string|null, status_approval: string|null, is_locked: bool,
     *   has_final_khs: bool, existing_khs: array|null,
     *   courses: array<int, array{id_kelas_kuliah: string, nilai_akhir: float|null,
     *     status: string|null, semester_ke: int|null, category: string|null}>
     * }>
     */
    public function manualNilaiContext(array $payload): array
    {
        $semesterId = $payload['id_semester'] ?? null;
        $targetSemesterKe = (int) ($payload['semester_ke'] ?? 0);
        $studentQuery = Mahasiswa::query()
            ->where(function ($builder) {
                $builder->whereNull('status')
                    ->orWhere('status', '!=', 'nonaktif');
            });

        if (!empty($payload['id_prodi'])) {
            $studentQuery->where('id_prodi', $payload['id_prodi']);
        }

        if (!empty($payload['angkatan'])) {
            $studentQuery->where('angkatan', $payload['angkatan']);
        }

        if (!empty($payload['q'])) {
            $search = trim($payload['q']);
            $studentQuery->where(function ($builder) use ($search) {
                $builder->where('nama_mahasiswa', 'like', '%' . $search . '%')
                    ->orWhere('nim', 'like', '%' . $search . '%');
            });
        }

        $students = $studentQuery
            ->with('prodi:id,nama_prodi')
            ->orderBy('angkatan')
            ->orderBy('nim')
            ->get();

        $studentIds = $students->pluck('id')->all();

        $krsMap = KRS::query()
            ->with(['details.kelasKuliah.kurikulumMataKuliah.mataKuliah:id,kode_mk,nama_mk,sks'])
            ->where('id_semester', $semesterId)
            ->whereIn('id_mahasiswa', $studentIds)
            ->where('status_approval', KRS::STATUS_APPROVED)
            ->get()
            ->keyBy('id_mahasiswa');

        $khsMap = KHS::query()
            ->where('id_semester', $semesterId)
            ->whereIn('id_mahasiswa', $studentIds)
            ->get()
            ->keyBy('id_mahasiswa');

        return $students
            ->filter(fn(Mahasiswa $student) => $krsMap->has($student->id))
            ->values()
            ->map(function (Mahasiswa $student) use ($krsMap, $khsMap, $targetSemesterKe) {
                $krs = $krsMap->get($student->id);
                $khs = $khsMap->get($student->id);
                $courses = $krs
                    ? $krs->details
                        ->map(fn($detail) => [
                            'id_kelas_kuliah' => $detail->id_kelas_kuliah,
                            'id_mata_kuliah' => $detail->kelasKuliah?->kurikulumMataKuliah?->id_mata_kuliah,
                            'kode_mk' => $detail->kode_mata_kuliah,
                            'nama_mk' => $detail->nama_mata_kuliah,
                            'sks' => (int) ($detail->sks ?? 0),
                            'nilai_akhir' => $detail->nilai_akhir !== null
                                ? (float) $detail->nilai_akhir
                                : null,
                            'status' => $detail->status,
                            'semester_ke' => $detail->kelasKuliah?->kurikulumMataKuliah?->semester_ke !== null
                                ? (int) $detail->kelasKuliah->kurikulumMataKuliah->semester_ke
                                : null,
                            'category' => $this->categorizeDetail($detail, $targetSemesterKe),
                        ])
                        ->values()
                        ->all()
                    : [];

                return [
                    'id_mahasiswa' => $student->id,
                    'nim' => $student->nim,
                    'nama_mahasiswa' => $student->nama_mahasiswa,
                    'prodi' => $student->prodi?->nama_prodi,
                    'status_approval' => $krs?->status_approval,
                    'is_locked' => (bool) ($krs?->is_locked ?? false),
                    'has_final_khs' => (bool) ($khs?->is_final ?? false),
                    'existing_khs' => $khs ? [
                        'id' => $khs->id,
                        'is_final' => (bool) $khs->is_final,
                    ] : null,
                    'courses' => $courses,
                ];
            })
            ->values()
            ->all();
    }

    private function categorizeDetail(KRSDetail $detail, int $targetSemesterKe): ?string
    {
        if ($targetSemesterKe <= 0) {
            return null;
        }

        $detailSemesterKe = (int) ($detail->kelasKuliah?->kurikulumMataKuliah?->semester_ke ?? 0);

        if ($detailSemesterKe === $targetSemesterKe) {
            return 'paket';
        }

        if ($detailSemesterKe > 0 && $detailSemesterKe < $targetSemesterKe) {
            return 'ulang';
        }

        return 'tambahan';
    }
}
