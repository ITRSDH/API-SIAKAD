<?php

namespace App\Services;

use InvalidArgumentException;
use App\Models\MasterData\Prodi;
use Illuminate\Support\Facades\DB;
use App\Models\MasterData\Kurikulum;
use App\Models\MasterData\MataKuliah;
use App\Models\MasterData\TahunAkademik;

class DropdownService
{
    public function get(string $type)
    {
        $available = [
            'prodi'     => fn() => $this->prodi(),
            // 'prodilist' => fn() => $this->prodiWithCountMK(),
            'semester'  => fn() => $this->semester(),
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

    // private function prodiWithCountMK()
    // {
    //     return Prodi::select(
    //         'id',
    //         'kode_prodi',
    //         DB::raw("CONCAT('(', jenjang_pendidikan, ') ', nama_prodi) as prodi"),
    //     )
    //         ->withCount('mataKuliah')
    //         ->get();
    // }

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
}
