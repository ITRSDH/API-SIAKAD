<?php

namespace App\Services\Khs;

use App\Models\Akademik\KRS;
use App\Models\Akademik\KRSDetail;
use App\Models\MasterData\Prodi;
use App\Models\MasterData\Semester;
use Illuminate\Support\Collection;

class KhsTemplateExportService
{
    public function build(array $filters): array
    {
        $semester = Semester::with('tahunAkademik')->findOrFail($filters['id_semester']);
        $prodi = Prodi::find($filters['id_prodi']);
        $semesterKe = (int) $filters['semester_ke'];

        $krsCollection = KRS::query()
            ->with([
                'mahasiswa.prodi:id,nama_prodi',
                'details.kelasKuliah.kurikulumMataKuliah.mataKuliah',
            ])
            ->where('id_semester', $filters['id_semester'])
            ->where('status_approval', KRS::STATUS_APPROVED)
            ->whereHas('mahasiswa', function ($query) use ($filters) {
                $query->where('id_prodi', $filters['id_prodi'])
                    ->where('angkatan', $filters['angkatan']);
            })
            ->whereHas('details.kelasKuliah.kurikulumMataKuliah', function ($query) use ($semesterKe) {
                // MK paket semester target + MK ulang (repeat) dari semester sebelumnya
                // — agar mahasiswa yang mengulang MK (misal gagal di semester 1, diulang di
                // semester 3) tetap dapat kolom pada template import.
                $query->where('semester_ke', '>', 0)
                    ->where('semester_ke', '<=', $semesterKe);
            })
            ->get();

        $subjects = $this->buildSubjects($krsCollection, $semesterKe);
        $rows = $this->buildRows($krsCollection, $subjects, $semesterKe);

        return [
            'metadata' => [
                'angkatan' => (int) $filters['angkatan'],
                'semester_ke' => $semesterKe,
                'semester_label' => trim(($semester->nama_semester ?? '-') . ' ' . ($semester->tahunAkademik?->tahun_akademik ?? '-')),
                'prodi_label' => $prodi?->nama_prodi ?? ($krsCollection->first()?->mahasiswa?->prodi?->nama_prodi ?? ''),
            ],
            'subjects' => $subjects->values()->all(),
            'rows' => $rows->values()->all(),
        ];
    }

    private function buildSubjects(Collection $krsCollection, int $semesterKe): Collection
    {
        return $krsCollection
            ->flatMap(function (KRS $krs) use ($semesterKe) {
                return $krs->details->filter(function (KRSDetail $detail) use ($semesterKe) {
                    $detailSemesterKe = (int) ($detail->kelasKuliah?->kurikulumMataKuliah?->semester_ke ?? 0);

                    return $detailSemesterKe > 0 && $detailSemesterKe <= $semesterKe;
                })->map(function (KRSDetail $detail) {
                    $mataKuliah = $detail->kelasKuliah?->kurikulumMataKuliah?->mataKuliah;

                    return [
                        'id_mata_kuliah' => $mataKuliah?->id,
                        'kode_mk' => $detail->kode_mata_kuliah,
                        'nama_mk' => $detail->nama_mata_kuliah,
                        'sks' => (int) $detail->sks,
                    ];
                });
            })
            ->filter(fn(array $subject) => filled($subject['kode_mk']))
            ->unique('kode_mk')
            ->sortBy('kode_mk')
            ->values();
    }

    private function buildRows(Collection $krsCollection, Collection $subjects, int $semesterKe): Collection
    {
        return $krsCollection
            ->sortBy(function (KRS $krs) {
                $nim = (string) ($krs->mahasiswa?->nim ?? '');

                return [
                    $this->extractNimSequenceNumber($nim),
                    $nim,
                ];
            })
            ->values()
            ->map(function (KRS $krs, int $index) use ($subjects, $semesterKe) {
                $detailsByKode = $krs->details
                    ->filter(function (KRSDetail $detail) use ($semesterKe) {
                        $detailSemesterKe = (int) ($detail->kelasKuliah?->kurikulumMataKuliah?->semester_ke ?? 0);

                        return $detailSemesterKe > 0 && $detailSemesterKe <= $semesterKe;
                    })
                    ->keyBy(fn(KRSDetail $detail) => $detail->kode_mata_kuliah);

                return [
                    'no' => $index + 1,
                    'nim' => $krs->mahasiswa?->nim,
                    'nama' => $krs->mahasiswa?->nama_mahasiswa,
                    'subjects' => $subjects->map(function (array $subject) use ($detailsByKode, $semesterKe) {
                        /** @var KRSDetail|null $detail */
                        $detail = $detailsByKode->get($subject['kode_mk']);
                        $detailSemesterKe = $detail
                            ? (int) ($detail->kelasKuliah?->kurikulumMataKuliah?->semester_ke ?? 0)
                            : 0;

                        return [
                            'kode_mk' => $subject['kode_mk'],
                            'nama_mk' => $subject['nama_mk'],
                            'sks' => (int) ($detail?->sks ?? $subject['sks']),
                            'taken' => (bool) $detail,
                            'is_repeat' => $detail !== null && $detailSemesterKe > 0 && $detailSemesterKe < $semesterKe,
                            'status' => $detail?->status,
                            'semester_ke' => $detailSemesterKe > 0 ? $detailSemesterKe : null,
                        ];
                    })->values()->all(),
                ];
            });
    }

    private function extractNimSequenceNumber(?string $nim): int
    {
        $normalizedNim = trim((string) $nim);
        if ($normalizedNim === '' || strlen($normalizedNim) <= 4) {
            return PHP_INT_MAX;
        }

        $suffix = substr($normalizedNim, 4);
        if ($suffix === false || $suffix === '') {
            return PHP_INT_MAX;
        }

        if (preg_match('/^(\d+)/', $suffix, $matches) !== 1) {
            return PHP_INT_MAX;
        }

        return (int) $matches[1];
    }
}
