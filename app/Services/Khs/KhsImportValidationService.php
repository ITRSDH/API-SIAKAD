<?php

namespace App\Services\Khs;

use App\Models\Akademik\KRS;
use App\Models\Akademik\KRSDetail;
use App\Models\MasterData\Mahasiswa;
use Illuminate\Support\Collection;

class KhsImportValidationService
{
    public function __construct(
        private readonly KhsCalculationService $calculationService,
        private readonly KhsRemarkService $remarkService,
        private readonly GradeConversionService $gradeConversionService
    ) {
    }

    public function validateParsedPayload(array $payload, array $context = []): array
    {
        $semesterId = $context['id_semester'] ?? null;
        $semesterKe = (int) ($payload['metadata']['semester_ke'] ?? 0);
        $usesSeparatedIpkColumn = $semesterKe > 1 || (($payload['metadata']['tail_mode'] ?? null) === 'separated');
        if (!$semesterId) {
            return [
                'summary' => [
                    'total_rows' => count($payload['rows'] ?? []),
                    'total_valid' => 0,
                    'total_error' => count($payload['rows'] ?? []),
                    'total_warning' => 0,
                    'total_mahasiswa_found' => 0,
                    'total_mahasiswa_missing' => count($payload['rows'] ?? []),
                    'total_mk_matched' => 0,
                    'total_mk_mismatched' => 0,
                    'total_keterangan_mismatch' => 0,
                ],
                'rows' => [],
                'errors' => [],
                'warnings' => [],
            ];
        }

        $rows = collect($payload['rows'] ?? []);
        $nimList = $rows->pluck('nim')
            ->filter()
            ->unique()
            ->values();

        $mahasiswaByNim = Mahasiswa::query()
            ->whereIn('nim', $nimList)
            ->get()
            ->keyBy('nim');

        $mahasiswaIds = $mahasiswaByNim->pluck('id')->values();
        $krsCollection = KRS::query()
            ->with([
                'details.kelasKuliah.kurikulumMataKuliah.mataKuliah',
            ])
            ->where('id_semester', $semesterId)
            ->whereIn('id_mahasiswa', $mahasiswaIds)
            ->get()
            ->keyBy('id_mahasiswa');

        $validatedRows = [];
        $errors = [];
        $warnings = [];
        $mkMatched = 0;
        $mkMismatched = 0;
        $mahasiswaFound = 0;
        $mahasiswaMissing = 0;
        $keteranganMismatch = 0;
        $mutuMismatch = 0;

        foreach ($rows as $row) {
            $rowErrors = [];
            $rowWarnings = [];
            if (blank($row['nim'] ?? null)) {
                $rowErrors[] = 'NIM wajib diisi.';
            }

            $mahasiswa = $mahasiswaByNim->get($row['nim']);
            $krs = $mahasiswa ? $krsCollection->get($mahasiswa->id) : null;

            if (!$mahasiswa) {
                $mahasiswaMissing++;
                $rowErrors[] = 'Mahasiswa dengan NIM tersebut tidak ditemukan.';
            } else {
                $mahasiswaFound++;
            }

            if ($mahasiswa && !$krs) {
                $rowErrors[] = 'KRS mahasiswa pada semester yang dipilih tidak ditemukan.';
            }

            $subjectResults = collect($row['subjects'] ?? [])->map(function (array $subject) use ($krs, &$rowErrors, &$rowWarnings, &$mkMatched, &$mkMismatched, &$mutuMismatch) {
                $match = null;
                if ($krs) {
                    $match = $krs->details->first(function (KRSDetail $detail) use ($subject) {
                        return $detail->kode_mata_kuliah === $subject['kode_mk'];
                    });
                }

                if ($match) {
                    $mkMatched++;
                    if (
                        filled($subject['nama_mk'] ?? null)
                        && filled($match->nama_mata_kuliah)
                        && mb_strtoupper(trim((string) $subject['nama_mk'])) !== mb_strtoupper(trim((string) $match->nama_mata_kuliah))
                    ) {
                        $rowWarnings[] = 'Nama mata kuliah Excel untuk kode ' . $subject['kode_mk'] . ' berbeda dengan data KRS.';
                    }

                    if ($match->status === KRSDetail::STATUS_DROP) {
                        $rowErrors[] = 'Mata kuliah ' . $subject['kode_mk'] . ' berstatus drop pada KRS mahasiswa.';
                    }
                } else {
                    $mkMismatched++;
                    $rowErrors[] = 'Mata kuliah ' . $subject['kode_mk'] . ' tidak ditemukan pada KRS mahasiswa.';
                }

                $resolved = $this->resolveSubjectGrade($subject, $rowErrors);

                if (
                    $resolved['mutu'] !== null
                    && $subject['mutu'] !== null
                    && round((float) $subject['mutu'], 2) !== round((float) $resolved['mutu'], 2)
                ) {
                    $mutuMismatch++;
                    $rowWarnings[] = 'Mutu Excel untuk mata kuliah ' . $subject['kode_mk'] . ' tidak sesuai hasil hitung sistem.';
                }

                return array_merge($subject, $resolved, [
                    'matched' => (bool) $match,
                    'id_krs_detail' => $match?->id,
                    'id_kelas_kuliah' => $match?->id_kelas_kuliah,
                    'id_mata_kuliah' => $match?->kelasKuliah?->kurikulumMataKuliah?->mataKuliah?->id,
                ]);
            })->all();

            if (($row['keterangan'] ?? null) === null) {
                $rowErrors[] = 'Keterangan wajib diisi.';
            }

            $summary = $this->buildSubjectSummary(collect($subjectResults));
            $resolvedIpk = $usesSeparatedIpkColumn
                ? $this->resolveManualIpk($row, $rowErrors)
                : $summary['ips'];
            if (($row['keterangan'] ?? null) !== null && !$this->remarkService->matchesExcelRemark($summary['ips'], $row['keterangan'])) {
                $keteranganMismatch++;
                $rowWarnings[] = 'Keterangan Excel tidak sesuai dengan rule sistem berdasarkan IPS.';
            }

            $validatedRow = array_merge($row, [
                'ipk_excel' => $usesSeparatedIpkColumn ? ($row['ipk_excel'] ?? null) : $summary['ips'],
                'mahasiswa' => $mahasiswa ? [
                    'id' => $mahasiswa->id,
                    'nim' => $mahasiswa->nim,
                    'nama_mahasiswa' => $mahasiswa->nama_mahasiswa,
                ] : null,
                'summary' => array_merge($summary, [
                    'ipk' => $resolvedIpk,
                ]),
                'subjects' => $subjectResults,
                'errors' => $rowErrors,
                'warnings' => $rowWarnings,
                'is_valid' => count($rowErrors) === 0,
            ]);

            foreach ($rowErrors as $message) {
                $errors[] = [
                    'row_number' => $row['row_number'] ?? null,
                    'nim' => $row['nim'] ?? null,
                    'error_type' => 'validation',
                    'message' => $message,
                    'payload' => $validatedRow,
                ];
            }

            foreach ($rowWarnings as $message) {
                $warnings[] = [
                    'row_number' => $row['row_number'] ?? null,
                    'nim' => $row['nim'] ?? null,
                    'error_type' => 'warning',
                    'message' => $message,
                    'payload' => $validatedRow,
                ];
            }

            $validatedRows[] = $validatedRow;
        }

        $validCount = collect($validatedRows)->where('is_valid', true)->count();

        return [
            'summary' => [
                'total_rows' => $rows->count(),
                'total_valid' => $validCount,
                'total_error' => count($errors),
                'total_warning' => count($warnings),
                'total_mahasiswa_found' => $mahasiswaFound,
                'total_mahasiswa_missing' => $mahasiswaMissing,
                'total_mk_matched' => $mkMatched,
                'total_mk_mismatched' => $mkMismatched,
                'total_keterangan_mismatch' => $keteranganMismatch,
                'total_mutu_mismatch' => $mutuMismatch,
            ],
            'rows' => $validatedRows,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private function buildSubjectSummary(Collection $subjects): array
    {
        $eligible = $subjects
            ->filter(fn(array $subject) => $subject['mutu'] !== null && $subject['sks'] !== null)
            ->map(function (array $subject) {
                $mutu = (float) $subject['mutu'];
                $bobotNilai = $subject['bobot_nilai'] ?? round(((int) $subject['sks']) * $mutu, 2);

                return [
                    'sks' => (int) $subject['sks'],
                    'mutu' => $mutu,
                    'bobot_nilai' => $bobotNilai,
                    'status' => $mutu >= 2.00 ? 'lulus' : 'tidak_lulus',
                ];
            })
            ->values();

        return $this->calculationService->calculateSummary($eligible);
    }

    private function resolveManualIpk(array $row, array &$rowErrors): ?float
    {
        $ipkExcel = $row['ipk_excel'] ?? null;

        if ($ipkExcel === null) {
            $rowErrors[] = 'IPK wajib diisi manual untuk semester di atas semester 1.';

            return null;
        }

        if ($ipkExcel < 0 || $ipkExcel > 4) {
            $rowErrors[] = 'IPK harus berada pada rentang 0 sampai 4.';

            return null;
        }

        return round((float) $ipkExcel, 2);
    }

    private function resolveSubjectGrade(array $subject, array &$rowErrors): array
    {
        $nilaiAkhir = $subject['nilai_akhir'];
        $nilaiHuruf = $subject['nilai_huruf'];
        $bobotNilai = $subject['bobot_nilai'];
        $mutu = $subject['mutu'];
        $sks = (int) ($subject['sks'] ?? 0);

        if ($nilaiAkhir === null) {
            $rowErrors[] = 'Nilai angka untuk mata kuliah ' . $subject['kode_mk'] . ' wajib diisi.';
        }

        if ($nilaiAkhir !== null && ($nilaiAkhir < 0 || $nilaiAkhir > 100)) {
            $rowErrors[] = 'Nilai angka untuk mata kuliah ' . $subject['kode_mk'] . ' tidak valid.';
        }

        if ($bobotNilai !== null && $bobotNilai < 0) {
            $rowErrors[] = 'Bobot nilai untuk mata kuliah ' . $subject['kode_mk'] . ' tidak valid.';
        }

        if ($mutu !== null && ($mutu < 0 || $mutu > 4)) {
            $rowErrors[] = 'Mutu untuk mata kuliah ' . $subject['kode_mk'] . ' harus berada pada rentang 0 sampai 4.';
        }

        if ($nilaiHuruf === null && $mutu === null && $nilaiAkhir !== null) {
            $fallback = $this->gradeConversionService->convertNumericScore((float) $nilaiAkhir);
            $nilaiHuruf = $fallback['nilai_huruf'];
            $mutu = $fallback['bobot_nilai'];
        } elseif ($nilaiHuruf !== null && $mutu === null) {
            $fallback = $this->gradeConversionService->convertLetterGrade((string) $nilaiHuruf);
            if ($fallback) {
                $nilaiHuruf = $fallback['nilai_huruf'];
                $mutu = $fallback['bobot_nilai'];
            } else {
                $rowErrors[] = 'Nilai huruf untuk mata kuliah ' . $subject['kode_mk'] . ' tidak dikenali.';
            }
        } elseif ($nilaiHuruf === null && $mutu !== null && $nilaiAkhir !== null) {
            $fallback = $this->gradeConversionService->convertNumericScore((float) $nilaiAkhir);
            $nilaiHuruf = $fallback['nilai_huruf'];
        }

        if ($bobotNilai === null && $mutu !== null && $sks > 0) {
            $bobotNilai = round($sks * (float) $mutu, 2);
        }

        return [
            'nilai_huruf' => $nilaiHuruf,
            'bobot_nilai' => $bobotNilai !== null ? round((float) $bobotNilai, 2) : null,
            'mutu' => $mutu !== null ? round((float) $mutu, 2) : null,
        ];
    }
}
