<?php

namespace App\Services;

use App\Models\MasterData\Dosen;
use App\Models\MasterData\Kurikulum;
use App\Models\MasterData\KurikulumMataKuliah;
use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\Prodi;
use App\Models\MasterData\TahunAkademik;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DropdownService
{
    public function get(string $type)
    {
        $available = [
            'prodi' => fn () => $this->prodi(),
            'semester' => fn () => $this->semester(),
            'kurikulum' => fn () => $this->kurikulum(),
            'kurikulum_matakuliah' => fn () => $this->kurikulum_matakuliah(),
            'dosen_pengajar' => fn () => $this->dosen_pengajar(),
            'dosen_wali' => fn () => $this->dosen_wali(),
            'mahasiswa_wali' => fn () => $this->mahasiswa_wali(),
        ];

        $types = explode(',', $type);
        $data = [];

        foreach ($types as $item) {
            $key = trim($item);

            if (! isset($available[$key])) {
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
        return KurikulumMataKuliah::with(['mataKuliah', 'kurikulum.semesterMulai.tahunAkademik'])
            ->get()
            ->map(function ($item) {
                $mulaiBerlaku = $item->kurikulum?->semesterMulai?->tahunAkademik
                    ? trim($item->kurikulum->semesterMulai->tahunAkademik->tahun_akademik.' '.$item->kurikulum->semesterMulai->nama_semester)
                    : null;

                $mk = $item->mataKuliah;

                return [
                    'id' => $item->id,
                    'id_kurikulum' => $item->id_kurikulum,
                    'id_prodi' => $item->kurikulum?->id_prodi,
                    'id_mata_kuliah' => $item->id_mata_kuliah,
                    'kode_mk' => $mk?->kode_mk,
                    'nama_mk' => $mk?->nama_mk,
                    'sks' => $mk?->sks ?? 0,
                    'sks_tatap_muka' => $mk?->sks_tatap_muka ?? 0,
                    'sks_praktikum' => $mk?->sks_praktikum ?? 0,
                    'sks_praktek_lapangan' => $mk?->sks_praktek_lapangan ?? 0,
                    'sks_simulasi' => $mk?->sks_simulasi ?? 0,
                    'semester_ke' => $item->semester_ke,
                    'status_mk' => $item->status_mk ?? ($item->is_wajib ? 'wajib' : 'pilihan'),
                    'is_wajib' => (bool) $item->is_wajib,
                    'nama_kurikulum' => $item->kurikulum?->nama_kurikulum ?? $item->kurikulum?->nama_struktur_mk,
                    'matakuliah' => ($mk?->kode_mk ?? '-')
                        .' - '.($mk?->nama_mk ?? '-')
                        .' (SKS '.($mk?->sks ?? 0).')'
                        .' - '
                        .'(Semester '.$item->semester_ke.')'
                        .' '
                        .($item->kurikulum?->nama_kurikulum ?? $item->kurikulum?->nama_struktur_mk)
                        .($mulaiBerlaku ? ' (Mulai '.$mulaiBerlaku.')' : ''),
                ];
            });
    }

    private function kurikulum()
    {
        return Kurikulum::with([
            'prodi:id,nama_prodi,jenjang_pendidikan',
            'semesterMulai.tahunAkademik:id,tahun_akademik',
        ])
            ->orderBy('nama_struktur_mk')
            ->get()
            ->map(function ($item) {
                $mulaiBerlaku = $item->semesterMulai?->tahunAkademik
                    ? trim($item->semesterMulai->tahunAkademik->tahun_akademik.' '.$item->semesterMulai->nama_semester)
                    : null;

                return [
                    'id' => $item->id,
                    'id_prodi' => $item->id_prodi,
                    'kode_kurikulum' => $item->kode_kurikulum,
                    'nama_struktur_mk' => $item->nama_struktur_mk,
                    'nama_kurikulum' => $item->nama_kurikulum,
                    'mulai_berlaku' => $mulaiBerlaku,
                    'kurikulum' => collect([
                        $item->kode_kurikulum,
                        $item->nama_struktur_mk,
                        $mulaiBerlaku ? 'Mulai '.$mulaiBerlaku : null,
                        $item->prodi ? '('.$item->prodi->jenjang_pendidikan.') '.$item->prodi->nama_prodi : null,
                    ])->filter()->implode(' - '),
                ];
            });
    }

    private function dosen_pengajar()
    {
        return Dosen::with('prodi')
            ->get()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'dosen_pengajar' => $item->nidn.' - '.$item->nama_dosen.' ('.$item->prodi->jenjang_pendidikan.' '.$item->prodi->nama_prodi.')',
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
                    'dosen_wali' => $item->nidn.' - '.$item->nama_dosen.' ('.
                        ($item->prodi->jenjang_pendidikan ?? '').' '.
                        ($item->prodi->nama_prodi ?? '').')',
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
                    'prodi' => ' ('.$item->prodi->jenjang_pendidikan.' '.$item->prodi->nama_prodi.')',
                ];
            });
    }
}
