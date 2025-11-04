<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NilaiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kelasMk = DB::table('kelas_mk')->where('kode_kelas_mk', 'IF101-A')->value('id');
        $mahasiswa = DB::table('mahasiswa')->where('nim', '241001')->value('id');
        $semesterAktif = DB::table('semester')->where('status', 'Aktif')->first()->id;

        $nilais = [
            [
                'id' => (string) Str::uuid(),
                'id_kelas_mk' => $kelasMk,
                'id_mahasiswa' => $mahasiswa,
                'id_semester' => $semesterAktif,
                'nilai_angka' => 85.50,
                'nilai_huruf' => 'A-',
                'bobot' => 3.70,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('nilai')->insert($nilais);
    }
}
