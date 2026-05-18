<?php

namespace App\Services;

use App\Models\MasterData\Dosen;
use App\Models\MasterData\KurikulumMataKuliah;
use App\Models\MasterData\Prodi;
use App\Models\MasterData\TahunAkademik;
use App\Models\MasterData\Mahasiswa;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DropdownService
{
    public function get(string $type)
    {
        $available = [
            'prodi'     => fn() => $this->prodi(),
            'semester'  => fn() => $this->semester(),
            'kurikulum_matakuliah'  => fn() => $this->kurikulum_matakuliah(),
            'dosen_pengajar'  => fn() => $this->dosen_pengajar(),
            'dosen_wali'  => fn() => $this->dosen_wali(),
            'mahasiswa_wali'  => fn() => $this->mahasiswa_wali(),
        ];

        $types = explode(',', $type);
        $data = [];

        foreach ($types as $item) {
            $key = trim($item);

            if (!isset($available[$key])) {
                throw new InvalidArgumentException("Dropdown type '{$key}' tidak valid.");
            }

            $data[$key] = $available[$key]();
        }

        return $data;
    }

    private function prodi()
    {
        return Prodi::select(
            'id',
            'kode_prodi',
            DB::raw("CONCAT('(', jenjang_pendidikan, ') ', nama_prodi) as prodi")
        )
            ->orderBy('nama_prodi')
            ->get();
    }

    private function semester()
    {
        return TahunAkademik::join('semester', 'semester.id_tahun_akademik', '=', 'tahun_akademik.id')
            ->select(
                'semester.id',
                DB::raw("CONCAT(tahun_akademik.tahun_akademik, ' ', semester.nama_semester) as semester")
            )
            ->orderBy('tahun_akademik.tahun_akademik', 'desc')
            ->orderBy('semester.nama_semester')
            ->get();
    }

    private function kurikulum_matakuliah()
    {
        return KurikulumMataKuliah::with(['mataKuliah', 'kurikulum'])
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    // 'matakuliah' => $item->kurikulum->nama_kurikulum . ' - ' . $item->mataKuliah->nama_mata_kuliah . ' (Semester ' . $item->semester_ke . ')'
                    'matakuliah' => $item->mataKuliah->kode_mk . ' - ' . $item->mataKuliah->nama_mk . ' (SKS ' . $item->mataKuliah->sks . ')' . ' - ' . ' (Semester ' . $item->semester_ke . ')' . '  ' . $item->kurikulum->nama_kurikulum
                ];
            });
    }

    private function dosen_pengajar()
    {
        return Dosen::with('prodi')
            ->get()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'dosen_pengajar' => $item->nidn . ' - ' . $item->nama_dosen . ' (' . $item->prodi->jenjang_pendidikan . ' ' . $item->prodi->nama_prodi . ')'
                ];
            });
    }

    private function dosen_wali()
    {
        return Dosen::with(['prodi', 'user'])
            ->whereHas('user.roles', function ($q) {
                $q->where('name', 'dosen');
            })
            ->whereDoesntHave('mahasiswaWali')
            ->select('id', 'nidn', 'nama_dosen', 'id_prodi', 'user_id')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'dosen_wali' => $item->nidn . ' - ' . $item->nama_dosen . ' (' .
                        ($item->prodi->jenjang_pendidikan ?? '') . ' ' .
                        ($item->prodi->nama_prodi ?? '') . ')'
                ];
            });
    }

    private function mahasiswa_wali()
    {
        return Mahasiswa::with('prodi')
            ->whereDoesntHave('dosenWali')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'nim' => $item->nim,
                    'nama_mahasiswa' => $item->nama_mahasiswa,
                    'prodi' => ' (' . $item->prodi->jenjang_pendidikan . ' ' . $item->prodi->nama_prodi . ')',
                ];
            });
    }
}
