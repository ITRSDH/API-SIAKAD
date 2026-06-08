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
        return [
            'id_kurikulum_induk' => $this->mahasiswaCurriculumContextService->resolveMahasiswaKurikulumIndukId($mahasiswa),
            'id_struktur_operasional' => $this->resolveActiveKurikulumId($mahasiswa),
            'id_kurikulum_operasional' => $this->resolveActiveKurikulumId($mahasiswa),
        ];
    }
}
