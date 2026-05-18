<?php

namespace App\Services;

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
        $mahasiswa = Mahasiswa::with('riwayatKurikulum')->find($mahasiswaId);
        if (!$mahasiswa) {
            return [$targetCourseId];
        }

        $resolvedTargetKurikulumId = $targetKurikulumId ?: $this->activeCurriculumService->resolveActiveKurikulumId($mahasiswa);
        if (!$resolvedTargetKurikulumId) {
            return [$targetCourseId];
        }

        $sourceKurikulumIds = $mahasiswa->riwayatKurikulum
            ->pluck('id_kurikulum')
            ->filter(fn($id) => filled($id) && $id !== $resolvedTargetKurikulumId)
            ->unique()
            ->values();

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
        $mahasiswa = Mahasiswa::with('riwayatKurikulum')->find($mahasiswaId);
        if (!$mahasiswa) {
            return MataKuliah::find($sourceCourseId);
        }

        $resolvedTargetKurikulumId = $targetKurikulumId ?: $this->activeCurriculumService->resolveActiveKurikulumId($mahasiswa);
        if (!$resolvedTargetKurikulumId) {
            return MataKuliah::find($sourceCourseId);
        }

        $sourceKurikulumIds = $mahasiswa->riwayatKurikulum
            ->pluck('id_kurikulum')
            ->filter(fn($id) => filled($id) && $id !== $resolvedTargetKurikulumId)
            ->unique()
            ->values();

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

        return $conversion?->mataKuliahTujuan ?: MataKuliah::find($sourceCourseId);
    }
}
