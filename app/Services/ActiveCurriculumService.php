<?php

namespace App\Services;

use App\Models\MasterData\Kurikulum;
use App\Models\MasterData\KurikulumMataKuliah;
use App\Models\MasterData\Mahasiswa;
use Illuminate\Support\Collection;

class ActiveCurriculumService
{
    public function __construct(
        private readonly MahasiswaCurriculumContextService $mahasiswaCurriculumContextService
    ) {
    }

    public function resolveActiveKurikulumId(Mahasiswa|string|null $mahasiswa): ?string
    {
        return $this->mahasiswaCurriculumContextService->resolveKrsKurikulumId($mahasiswa);
    }

    public function resolveActiveKurikulum(Mahasiswa|string|null $mahasiswa): ?Kurikulum
    {
        $resolvedKurikulumId = $this->resolveActiveKurikulumId($mahasiswa);

        return $resolvedKurikulumId
            ? Kurikulum::query()
                ->with(['semesterMulai.tahunAkademik', 'kurikulumMataKuliah.mataKuliah'])
                ->find($resolvedKurikulumId)
            : null;
    }

    public function resolvePackageItemsForSemester(Mahasiswa|string|null $mahasiswa, int $semesterKe): Collection
    {
        $resolvedKurikulumId = $this->resolveActiveKurikulumId($mahasiswa);

        if (!$resolvedKurikulumId) {
            return collect();
        }

        return KurikulumMataKuliah::query()
            ->where('id_kurikulum', $resolvedKurikulumId)
            ->where('semester_ke', $semesterKe)
            ->with('mataKuliah')
            ->orderByDesc('is_wajib')
            ->orderBy('id')
            ->get();
    }

    public function resolveCurriculumContext(Mahasiswa|string|null $mahasiswa): array
    {
        $activeKurikulum = $this->resolveActiveKurikulum($mahasiswa);
        $induk = $activeKurikulum?->kurikulumInduk;
        $indukId = $induk?->id ?? $this->mahasiswaCurriculumContextService->resolveMahasiswaKurikulumIndukId($mahasiswa);
        $operationalId = $activeKurikulum?->id;

        return [
            'id_kurikulum' => $operationalId,
            'id_kurikulum_sumber' => $indukId,
            'id_kurikulum_dasar' => $indukId,
            'id_kurikulum_induk' => $indukId,
            'id_struktur_operasional' => $operationalId,
            'id_kurikulum_operasional' => $operationalId,
            'kurikulum_induk' => $induk ? [
                'id' => $induk->id,
                'nama_kurikulum' => $induk->nama_kurikulum,
                'keterangan' => $induk->nama_kurikulum,
                'kode_kurikulum' => $induk->kode_kurikulum,
                'tahun_kurikulum' => $induk->tahun_kurikulum,
                'jenis_kurikulum' => $induk->jenisKurikulum ? [
                    'id' => $induk->jenisKurikulum->id,
                    'kode_jenis' => $induk->jenisKurikulum->kode_jenis,
                    'nama_jenis_kurikulum' => $induk->jenisKurikulum->nama_jenis_kurikulum,
                ] : null,
            ] : null,
            'struktur_operasional' => $activeKurikulum ? [
                'id' => $activeKurikulum->id,
                'nama_struktur_mk' => $activeKurikulum->nama_struktur_mk,
                'nama_kurikulum' => $activeKurikulum->nama_kurikulum,
                'id_semester' => $activeKurikulum->id_semester,
                'id_kurikulum_induk' => $activeKurikulum->id_kurikulum_induk,
                'mulai_berlaku' => $activeKurikulum->semesterMulai?->tahunAkademik
                    ? trim($activeKurikulum->semesterMulai->tahunAkademik->tahun_akademik . ' ' . $activeKurikulum->semesterMulai->nama_semester)
                    : null,
            ] : null,
        ];
    }
}
