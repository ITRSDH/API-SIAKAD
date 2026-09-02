<?php

namespace App\Services;

use App\Models\Akademik\KRS;
use App\Models\Akademik\KRSDetail;
use App\Models\MasterData\KonversiMataKuliah;
use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\MataKuliah;
use Illuminate\Support\Collection;

class CurriculumConversionService
{
    public function __construct(
        private readonly ActiveCurriculumService $activeCurriculumService
    ) {
    }

    public function getRecognizedSourceCourseIdsForTarget(string $mahasiswaId, string $targetCourseId, ?string $targetKurikulumId = null): array
    {
        $mahasiswa = Mahasiswa::find($mahasiswaId);
        if (!$mahasiswa) {
            return [$targetCourseId];
        }

        $resolvedTargetKurikulumId = $targetKurikulumId ?: $this->activeCurriculumService->resolveActiveKurikulumId($mahasiswa);
        if (!$resolvedTargetKurikulumId) {
            return [$targetCourseId];
        }

        $sourceKurikulumIds = $this->resolveSourceKurikulumIds($mahasiswa, $resolvedTargetKurikulumId);

        if ($sourceKurikulumIds->isEmpty()) {
            return [$targetCourseId];
        }

        $recognizedIds = KonversiMataKuliah::query()
            ->where('id_kurikulum_tujuan', $resolvedTargetKurikulumId)
            ->where('id_mata_kuliah_tujuan', $targetCourseId)
            ->where('status_konversi', KonversiMataKuliah::STATUS_DIAKUI)
            ->whereIn('id_kurikulum_asal', $sourceKurikulumIds->all())
            ->pluck('id_mata_kuliah_asal')
            ->push($targetCourseId)
            ->unique()
            ->values()
            ->all();

        return $recognizedIds;
    }

    public function resolveTranscriptCourse(string $mahasiswaId, string $sourceCourseId, ?string $targetKurikulumId = null): ?MataKuliah
    {
        $mahasiswa = Mahasiswa::find($mahasiswaId);
        if (!$mahasiswa) {
            return MataKuliah::find($sourceCourseId);
        }

        $resolvedTargetKurikulumId = $targetKurikulumId ?: $this->activeCurriculumService->resolveActiveKurikulumId($mahasiswa);
        if (!$resolvedTargetKurikulumId) {
            return MataKuliah::find($sourceCourseId);
        }

        $sourceKurikulumIds = $this->resolveSourceKurikulumIds($mahasiswa, $resolvedTargetKurikulumId);

        $conversion = null;
        if ($sourceKurikulumIds->isNotEmpty()) {
            $conversion = KonversiMataKuliah::query()
                ->with('mataKuliahTujuan')
                ->where('id_kurikulum_tujuan', $resolvedTargetKurikulumId)
                ->where('id_mata_kuliah_asal', $sourceCourseId)
                ->where('status_konversi', KonversiMataKuliah::STATUS_DIAKUI)
                ->whereIn('id_kurikulum_asal', $sourceKurikulumIds->all())
                ->first();
        }

        if ($conversion?->mataKuliahTujuan) {
            return $conversion->mataKuliahTujuan;
        }

        $sourceCourse = MataKuliah::find($sourceCourseId);
        if (!$sourceCourse) {
            return null;
        }

        $matchedByCode = MataKuliah::query()
            ->where('kode_mk', $sourceCourse->kode_mk)
            ->whereHas('kurikulum', function ($query) use ($resolvedTargetKurikulumId) {
                $query->where('kurikulum.id', $resolvedTargetKurikulumId);
            })
            ->orderBy('id')
            ->first();

        return $matchedByCode ?: $sourceCourse;
    }

    /**
     * Struktur kurikulum "asal" diturunkan dari KRS historis mahasiswa
     * (mata kuliah yang pernah ditempuh) — pengganti histori penugasan
     * mahasiswa ke kurikulum yang sudah dihapus.
     */
    private function resolveSourceKurikulumIds(Mahasiswa $mahasiswa, string $targetKurikulumId): Collection
    {
        $sourceKurikulumIds = KRSDetail::query()
            ->whereHas('krs', function ($query) use ($mahasiswa) {
                $query->where('id_mahasiswa', $mahasiswa->id);
            })
            ->whereHas('kelasKuliah.kurikulumMataKuliah', function ($query) use ($targetKurikulumId) {
                $query->where('id_kurikulum', '!=', $targetKurikulumId);
            })
            ->with('kelasKuliah.kurikulumMataKuliah:id,id_kurikulum')
            ->limit(200)
            ->get()
            ->pluck('kelasKuliah.kurikulumMataKuliah.id_kurikulum')
            ->filter(fn($id) => filled($id))
            ->unique()
            ->values();

        if ($sourceKurikulumIds->isEmpty()) {
            return collect([$targetKurikulumId]);
        }

        return $sourceKurikulumIds;
    }
}
