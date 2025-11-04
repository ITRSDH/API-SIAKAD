<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BebanAjarDosenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dosen = DB::table('dosen')->where('nidn', '1234567890')->value('id');
        $kelasMk = DB::table('kelas_mk')->where('kode_kelas_mk', 'IF101-A')->value('id');
        $semesterAktif = DB::table('semester')->where('status', 'Aktif')->first()->id;

        $bebanAjar = [
            [
                'id' => (string) Str::uuid(),
                'id_dosen' => $dosen,
                'id_kelas_mk' => $kelasMk,
                'id_semester' => $semesterAktif,
                'jumlah_jam' => 4, // 2 sks teori + 1 sks praktikum = 4 jam per minggu (asumsi)
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('beban_ajar_dosen')->insert($bebanAjar);
    }
}
