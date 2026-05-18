<?php

namespace App\Services;

use App\Models\MasterData\Kurikulum;
use App\Models\MasterData\Mahasiswa;

class ActiveCurriculumService
{
    public function resolveActiveKurikulumId(Mahasiswa|string|null $mahasiswa): ?string
    {
        $resolvedMahasiswa = $this->resolveMahasiswa($mahasiswa);
        if (!$resolvedMahasiswa) {
            return null;
        }

        return $resolvedMahasiswa->getActiveKurikulumId();
    }

    public function resolveActiveKurikulum(Mahasiswa|string|null $mahasiswa): ?Kurikulum
    {
        $resolvedMahasiswa = $this->resolveMahasiswa($mahasiswa);
        if (!$resolvedMahasiswa) {
            return null;
        }

        return $resolvedMahasiswa->getActiveKurikulum();
    }

    private function resolveMahasiswa(Mahasiswa|string|null $mahasiswa): ?Mahasiswa
    {
        if ($mahasiswa instanceof Mahasiswa) {
            return $mahasiswa;
        }

        if (!filled($mahasiswa)) {
            return null;
        }

        return Mahasiswa::with('riwayatKurikulum')->find($mahasiswa);
    }
}
