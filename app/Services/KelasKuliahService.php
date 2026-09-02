<?php

namespace App\Services;

use App\Models\Akademik\KRS;
use App\Models\Akademik\KRSDetail;
use App\Models\MasterData\KelasKuliah;
use App\Models\MasterData\Mahasiswa;
use App\Services\Krs\CourseRegistrationValidationService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KelasKuliahService
{
    public function __construct(
        private readonly CourseRegistrationValidationService $courseRegistrationValidationService
    ) {}

    /**
     * Jalankan proses pendaftaran KRS untuk sekumpulan mahasiswa.
     *
     * @param  string[]  $mahasiswaIds
     */
    public function registerKrsMahasiswa(array $mahasiswaIds, string $kelasKuliahId): array
    {
        $kelasKuliah = $this->loadKelasForKrsRegistration($kelasKuliahId);
        $targetMataKuliahId = $kelasKuliah->kurikulumMataKuliah?->id_mata_kuliah;
        $candidateSks = (int) ($kelasKuliah->kurikulumMataKuliah?->mataKuliah?->sks ?? 0);

        $mahasiswaItems = Mahasiswa::query()
            ->whereIn('id', $mahasiswaIds)
            ->get()
            ->keyBy('id');

        $krsByMahasiswa = KRS::query()
            ->with([
                'details.kelasKuliah.kurikulumMataKuliah',
                'details.kelasKuliah.jadwal',
            ])
            ->where('id_semester', $kelasKuliah->id_semester)
            ->whereIn('id_mahasiswa', array_keys($mahasiswaItems->all()))
            ->get()
            ->keyBy('id_mahasiswa');

        // Preload data kelulusan prasyarat untuk SEMUA mahasiswa sekaligus
        // (1 query agregat), sehingga loop di bawah tidak menjalankan query
        // KRSDetail::exists() per mahasiswa per prasyarat.
        $passedPrerequisiteDetails = $this->loadPassedPrerequisiteDetailsByMahasiswa(
            array_keys($mahasiswaItems->all())
        );

        $results = [];
        $registeredCount = 0;
        $alreadyCount = 0;
        $failedCount = 0;

        foreach ($mahasiswaIds as $mahasiswaId) {
            $mahasiswa = $mahasiswaItems->get($mahasiswaId);

            if (! $mahasiswa) {
                $results[] = [
                    'id_mahasiswa' => $mahasiswaId,
                    'status' => 'failed',
                    'message' => 'Mahasiswa tidak ditemukan.',
                ];
                $failedCount++;

                continue;
            }

            $krs = $krsByMahasiswa->get($mahasiswa->id);
            $assessment = $this->assessMahasiswaRegistrationCandidate(
                $mahasiswa,
                $kelasKuliah,
                $krs,
                $targetMataKuliahId,
                $candidateSks,
                $passedPrerequisiteDetails
            );

            if ($assessment['already_registered']) {
                $results[] = [
                    'id_mahasiswa' => $mahasiswa->id,
                    'nim' => $mahasiswa->nim,
                    'nama_mahasiswa' => $mahasiswa->nama_mahasiswa,
                    'status' => 'skipped',
                    'message' => 'Mahasiswa sudah terdaftar pada kelas ini.',
                ];
                $alreadyCount++;

                continue;
            }

            if (! $assessment['can_register']) {
                $results[] = [
                    'id_mahasiswa' => $mahasiswa->id,
                    'nim' => $mahasiswa->nim,
                    'nama_mahasiswa' => $mahasiswa->nama_mahasiswa,
                    'status' => 'failed',
                    'message' => $assessment['reason'] ?: 'Mahasiswa belum bisa didaftarkan ke kelas ini.',
                ];
                $failedCount++;

                continue;
            }

            try {
                $registration = DB::transaction(function () use ($mahasiswa, $kelasKuliah, $krs) {
                    $draftKrs = $krs;

                    if (! $draftKrs) {
                        $draftKrs = KRS::create([
                            'id_mahasiswa' => $mahasiswa->id,
                            'id_semester' => $kelasKuliah->id_semester,
                            'tanggal_pengajuan' => now(),
                            'status_approval' => KRS::STATUS_REVISED,
                            'total_sks' => 0,
                            'is_locked' => false,
                        ]);
                    }

                    $existingDetail = KRSDetail::query()
                        ->where('id_krs', $draftKrs->id)
                        ->where('id_kelas_kuliah', $kelasKuliah->id)
                        ->first();

                    if ($existingDetail) {
                        return ['status' => 'already_registered'];
                    }

                    $kelasKuliah->refresh();
                    if ($kelasKuliah->isPenuh()) {
                        return ['status' => 'class_full'];
                    }

                    KRSDetail::create([
                        'id_krs' => $draftKrs->id,
                        'id_kelas_kuliah' => $kelasKuliah->id,
                        'status' => KRSDetail::STATUS_TERDAFTAR,
                    ]);

                    $draftKrs->update([
                        'total_sks' => $draftKrs->calculateTotalSks(),
                    ]);

                    return [
                        'status' => 'registered',
                        'id_krs' => $draftKrs->id,
                    ];
                });

                if (($registration['status'] ?? null) === 'already_registered') {
                    $results[] = [
                        'id_mahasiswa' => $mahasiswa->id,
                        'nim' => $mahasiswa->nim,
                        'nama_mahasiswa' => $mahasiswa->nama_mahasiswa,
                        'status' => 'skipped',
                        'message' => 'Mahasiswa sudah terdaftar pada kelas ini.',
                    ];
                    $alreadyCount++;

                    continue;
                }

                if (($registration['status'] ?? null) === 'class_full') {
                    $results[] = [
                        'id_mahasiswa' => $mahasiswa->id,
                        'nim' => $mahasiswa->nim,
                        'nama_mahasiswa' => $mahasiswa->nama_mahasiswa,
                        'status' => 'failed',
                        'message' => 'Kelas sudah penuh saat proses pendaftaran dijalankan.',
                    ];
                    $failedCount++;

                    continue;
                }

                $results[] = [
                    'id_mahasiswa' => $mahasiswa->id,
                    'nim' => $mahasiswa->nim,
                    'nama_mahasiswa' => $mahasiswa->nama_mahasiswa,
                    'status' => 'registered',
                    'message' => 'Mahasiswa berhasil didaftarkan ke kelas ini.',
                ];
                $registeredCount++;
            } catch (Exception $e) {
                $results[] = [
                    'id_mahasiswa' => $mahasiswa->id,
                    'nim' => $mahasiswa->nim,
                    'nama_mahasiswa' => $mahasiswa->nama_mahasiswa,
                    'status' => 'failed',
                    'message' => 'Gagal mendaftarkan mahasiswa: '.$e->getMessage(),
                ];
                $failedCount++;
            }
        }

        return [
            'registered_count' => $registeredCount,
            'already_registered_count' => $alreadyCount,
            'failed_count' => $failedCount,
            'results' => $results,
        ];
    }

    public function assessMahasiswaRegistrationCandidate(
        Mahasiswa $mahasiswa,
        KelasKuliah $kelasKuliah,
        ?KRS $krs,
        ?string $targetMataKuliahId,
        int $candidateSks,
        ?Collection $passedPrerequisiteDetails = null
    ): array {
        $details = collect($krs?->details ?? []);
        $existingDetail = $details->firstWhere('id_kelas_kuliah', $kelasKuliah->id);

        if ($existingDetail) {
            return [
                'already_registered' => true,
                'can_register' => false,
                'state' => 'registered',
                'state_label' => 'Sudah terdaftar',
                'state_variant' => 'success',
                'reason' => 'Mahasiswa sudah terdaftar pada kelas ini.',
            ];
        }

        if (strtolower((string) $mahasiswa->status) !== 'aktif') {
            return [
                'already_registered' => false,
                'can_register' => false,
                'state' => 'inactive',
                'state_label' => 'Mahasiswa tidak aktif',
                'state_variant' => 'secondary',
                'reason' => 'Mahasiswa berstatus '.($mahasiswa->status ?? 'tidak aktif').'.',
            ];
        }

        if ($krs && ! $krs->isEditable()) {
            return [
                'already_registered' => false,
                'can_register' => false,
                'state' => 'locked',
                'state_label' => 'KRS tidak bisa diubah',
                'state_variant' => 'warning',
                'reason' => 'Draft KRS mahasiswa pada semester ini tidak dapat diubah.',
            ];
        }

        if ($kelasKuliah->isPenuh()) {
            return [
                'already_registered' => false,
                'can_register' => false,
                'state' => 'class_full',
                'state_label' => 'Kelas penuh',
                'state_variant' => 'danger',
                'reason' => 'Kapasitas kelas sudah penuh.',
            ];
        }

        $prerequisiteCheck = $this->validatePrerequisites(
            $mahasiswa->id,
            $kelasKuliah->kurikulumMataKuliah?->mataKuliah,
            $passedPrerequisiteDetails
        );

        if (! $prerequisiteCheck['passed']) {
            return [
                'already_registered' => false,
                'can_register' => false,
                'state' => 'prerequisite',
                'state_label' => 'Prasyarat belum terpenuhi',
                'state_variant' => 'warning',
                'reason' => $prerequisiteCheck['message'],
            ];
        }

        if ($targetMataKuliahId && $this->hasDuplicateCourseSelection($details, $targetMataKuliahId)) {
            return [
                'already_registered' => false,
                'can_register' => false,
                'state' => 'duplicate_course',
                'state_label' => 'Matakuliah sudah diambil',
                'state_variant' => 'warning',
                'reason' => 'Matakuliah ini sudah terdaftar pada kelas lain di KRS mahasiswa.',
            ];
        }

        $currentSks = (int) ($krs?->total_sks ?? 0);
        if (($currentSks + $candidateSks) > 24) {
            return [
                'already_registered' => false,
                'can_register' => false,
                'state' => 'sks_limit',
                'state_label' => 'Melebihi batas SKS',
                'state_variant' => 'warning',
                'reason' => 'Penambahan kelas ini akan melebihi batas maksimal 24 SKS.',
            ];
        }

        if ($this->hasScheduleConflict($details, $kelasKuliah)) {
            return [
                'already_registered' => false,
                'can_register' => false,
                'state' => 'schedule_conflict',
                'state_label' => 'Jadwal bentrok',
                'state_variant' => 'danger',
                'reason' => 'Jadwal kelas ini bertabrakan dengan kelas lain di KRS mahasiswa.',
            ];
        }

        return [
            'already_registered' => false,
            'can_register' => true,
            'state' => 'available',
            'state_label' => 'Siap didaftarkan',
            'state_variant' => 'primary',
            'reason' => null,
        ];
    }

    public function validatePrerequisites(
        string $mahasiswaId,
        $mataKuliah,
        ?Collection $passedPrerequisiteDetails = null
    ): array {
        return $this->courseRegistrationValidationService->validatePrerequisites(
            $mahasiswaId,
            $mataKuliah,
            $passedPrerequisiteDetails
        );
    }

    /**
     * Muat semua KRSDetail ber-status LULUS dari KRS ber-status APPROVED untuk
     * sekumpulan mahasiswa dalam satu query agregat. Dipakai sebagai sumber data
     * in-memory untuk evaluasi prasyarat di dalam loop registrasi KRS, sehingga
     * menghindari query per-mahasiswa-per-prasyarat (N+1).
     *
     * @param  string[]  $mahasiswaIds
     */
    private function loadPassedPrerequisiteDetailsByMahasiswa(array $mahasiswaIds): Collection
    {
        if ($mahasiswaIds === []) {
            return collect();
        }

        return KRSDetail::query()
            ->with([
                'krs:id,id_mahasiswa',
                'kelasKuliah.kurikulumMataKuliah:id,id_mata_kuliah',
            ])
            ->where('status', KRSDetail::STATUS_LULUS)
            ->whereHas('krs', function ($query) use ($mahasiswaIds) {
                $query->whereIn('id_mahasiswa', $mahasiswaIds)
                    ->where('status_approval', KRS::STATUS_APPROVED);
            })
            ->get();
    }

    public function resolveRepeatHistoryByMahasiswa(array $mahasiswaIds, ?string $targetMataKuliahId, string $currentSemesterId): Collection
    {
        if ($mahasiswaIds === [] || ! filled($targetMataKuliahId)) {
            return collect();
        }

        return KRSDetail::query()
            ->with([
                'krs.semester.tahunAkademik',
                'kelasKuliah.kurikulumMataKuliah.mataKuliah',
            ])
            ->whereIn('status', [KRSDetail::STATUS_LULUS, KRSDetail::STATUS_TIDAK_LULUS])
            ->whereHas('krs', function ($query) use ($mahasiswaIds, $currentSemesterId) {
                $query->whereIn('id_mahasiswa', $mahasiswaIds)
                    ->where('status_approval', KRS::STATUS_APPROVED)
                    ->where('id_semester', '!=', $currentSemesterId);
            })
            ->whereHas('kelasKuliah.kurikulumMataKuliah', function ($query) use ($targetMataKuliahId) {
                $query->where('id_mata_kuliah', $targetMataKuliahId);
            })
            ->get()
            ->groupBy(fn (KRSDetail $detail) => $detail->krs?->id_mahasiswa)
            ->map(function (Collection $items) {
                $latest = $items->sortByDesc(function (KRSDetail $detail) {
                    return optional($detail->krs)->tanggal_pengajuan ?? $detail->created_at;
                })->first();

                if (! $latest || $latest->status !== KRSDetail::STATUS_TIDAK_LULUS) {
                    return null;
                }

                return [
                    'status' => $latest->status,
                    'nilai_huruf' => $latest->nilai_huruf,
                    'nilai_akhir' => $latest->nilai_akhir,
                    'bobot_nilai' => $latest->bobot_nilai,
                    'semester' => $latest->krs?->semester?->tahunAkademik?->tahun_akademik
                        ? trim(($latest->krs->semester->tahunAkademik->tahun_akademik ?? '').' '.($latest->krs->semester->nama_semester ?? ''))
                        : null,
                ];
            })
            ->filter();
    }

    public function hasDuplicateCourseSelection(Collection $details, string $targetMataKuliahId): bool
    {
        return $this->courseRegistrationValidationService->hasDuplicateCourseSelection($details, $targetMataKuliahId);
    }

    public function hasScheduleConflict(Collection $details, KelasKuliah $kelasKuliah): bool
    {
        return $this->courseRegistrationValidationService->hasScheduleConflict($details, $kelasKuliah);
    }

    public function isTimeOverlap(string $start1, string $end1, string $start2, string $end2): bool
    {
        return $this->courseRegistrationValidationService->isTimeOverlap($start1, $end1, $start2, $end2);
    }

    public function loadKelasForKrsRegistration(string $id): KelasKuliah
    {
        return KelasKuliah::query()
            ->with([
                'kurikulumMataKuliah.mataKuliah.prasyarat.mataKuliahPrasyarat',
                'jadwal',
            ])
            ->findOrFail($id);
    }
}
