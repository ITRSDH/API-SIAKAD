<?php

namespace App\Services\Krs;

use App\Models\Akademik\KRS;
use App\Models\Akademik\KRSDetail;
use App\Models\MasterData\KelasKuliah;
use App\Services\CurriculumConversionService;
use Illuminate\Support\Collection;

/**
 * Sumber kebenaran tunggal untuk aturan validasi registrasi mata kuliah / KRS
 * yang bersifat pure dan dipakai bersama oleh domain KRS (KRSMahasiswaController)
 * dan registrasi KRS massal (KelasKuliahService).
 *
 * Menampung logika yang TIDAK boleh duplikat antar komponen:
 *  - deteksi bentrok jadwal (hari + overlap jam)
 *  - validasi prasyarat mata kuliah
 *  - deteksi pemilihan mata kuliah ganda
 */
class CourseRegistrationValidationService
{
    public function __construct(
        private readonly CurriculumConversionService $curriculumConversionService
    ) {}

    /**
     * Cek apakah dua rentang waktu saling tumpang tindih.
     * Konvensi string jam "HH:MM" dapat dibandingkan secara leksikografis.
     */
    public function isTimeOverlap(string $start1, string $end1, string $start2, string $end2): bool
    {
        return ($start1 < $end2) && ($start2 < $end1);
    }

    /**
     * Deteksi bentrok jadwal antara kandidat kelas kuliah dengan koleksi detail
     * KRS yang SUDAH ada. Kontrak: koleksi TIDAK boleh memuat kandidat itu sendiri
     * (kandidat belum terdaftar di KRS). Semua relasi kelasKuliah.jadwal pada item
     * koleksi harus sudah di-load.
     *
     * @param  Collection<int, KRSDetail>  $details
     */
    public function hasScheduleConflict(Collection $details, KelasKuliah $kelasKuliah): bool
    {
        foreach ($details as $detail) {
            $existingClass = $detail->kelasKuliah;

            if (! $existingClass) {
                continue;
            }

            foreach ($kelasKuliah->jadwal as $candidateSchedule) {
                foreach ($existingClass->jadwal as $existingSchedule) {
                    if (
                        $candidateSchedule->hari === $existingSchedule->hari
                        && $this->isTimeOverlap(
                            $candidateSchedule->jam_mulai,
                            $candidateSchedule->jam_selesai,
                            $existingSchedule->jam_mulai,
                            $existingSchedule->jam_selesai
                        )
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Cek apakah target mata kuliah sudah terpilih pada kelas lain di KRS yang sama.
     * Relasi kelasKuliah.kurikulumMataKuliah pada item koleksi harus sudah di-load.
     *
     * @param  Collection<int, KRSDetail>  $details
     */
    public function hasDuplicateCourseSelection(Collection $details, string $targetMataKuliahId): bool
    {
        return $details->contains(function (KRSDetail $detail) use ($targetMataKuliahId) {
            return (string) ($detail->kelasKuliah?->kurikulumMataKuliah?->id_mata_kuliah ?? '') === $targetMataKuliahId;
        });
    }

    /**
     * Validasi prasyarat mata kuliah terhadap riwayat KRS mahasiswa.
     *
     * Mendukung dua sumber kelulusan:
     *  - in-memory (batch): berikan $passedPrerequisiteDetails berisi KRSDetail
     *    ber-status LULUS dari KRS APPROVED (dari 1 query agregat) untuk
     *    menghindari N+1 pada registrasi massal;
     *  - query langsung: saat $passedPrerequisiteDetails === null, evaluasi
     *    dilakukan per-prasyarat melalui query DB.
     *
     * @param  Collection<int, KRSDetail>|null  $passedPrerequisiteDetails
     */
    public function validatePrerequisites(
        string $mahasiswaId,
        $mataKuliah,
        ?Collection $passedPrerequisiteDetails = null
    ): array {
        if (! $mataKuliah) {
            return [
                'passed' => false,
                'message' => 'Data mata kuliah tidak ditemukan.',
                'requirements' => [],
            ];
        }

        $requirements = [];

        foreach ($mataKuliah->prasyarat ?? [] as $prasyarat) {
            $mkPrasyarat = $prasyarat->mataKuliahPrasyarat;

            if (! $mkPrasyarat) {
                continue;
            }

            $equivalentCourseIds = $this->curriculumConversionService
                ->getRecognizedSourceCourseIdsForTarget($mahasiswaId, $mkPrasyarat->id);

            if ($passedPrerequisiteDetails !== null) {
                $hasPassed = $passedPrerequisiteDetails->contains(function (KRSDetail $detail) use ($mahasiswaId, $equivalentCourseIds, $prasyarat) {
                    return (string) ($detail->krs?->id_mahasiswa ?? '') === $mahasiswaId
                        && in_array(
                            (string) ($detail->kelasKuliah?->kurikulumMataKuliah?->id_mata_kuliah ?? ''),
                            array_map('strval', $equivalentCourseIds),
                            true
                        )
                        && $detail->status === KRSDetail::STATUS_LULUS
                        && (float) $detail->bobot_nilai >= (float) $prasyarat->min_bobot_nilai;
                });
            } else {
                $hasPassed = KRSDetail::query()
                    ->whereHas('krs', function ($query) use ($mahasiswaId) {
                        $query->where('id_mahasiswa', $mahasiswaId)
                            ->where('status_approval', KRS::STATUS_APPROVED);
                    })
                    ->whereHas('kelasKuliah.kurikulumMataKuliah.mataKuliah', function ($query) use ($equivalentCourseIds) {
                        $query->whereIn('mata_kuliah.id', $equivalentCourseIds);
                    })
                    ->where('status', KRSDetail::STATUS_LULUS)
                    ->where('bobot_nilai', '>=', $prasyarat->min_bobot_nilai)
                    ->exists();
            }

            $requirements[] = [
                'id_mata_kuliah_prasyarat' => $mkPrasyarat->id,
                'kode_mk' => $mkPrasyarat->kode_mk,
                'nama_mk' => $mkPrasyarat->nama_mk,
                'min_bobot_nilai' => $prasyarat->min_bobot_nilai,
                'is_passed' => $hasPassed,
            ];
        }

        $missing = array_values(array_filter($requirements, fn ($item) => ! $item['is_passed']));

        if ($missing !== []) {
            $first = $missing[0];

            return [
                'passed' => false,
                'message' => "Prasyarat {$first['kode_mk']} - {$first['nama_mk']} belum terpenuhi",
                'requirements' => $requirements,
            ];
        }

        return [
            'passed' => true,
            'message' => null,
            'requirements' => $requirements,
        ];
    }
}
