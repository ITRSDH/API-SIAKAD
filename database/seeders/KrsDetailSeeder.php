<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KrsDetailSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $krs = DB::table('krs')->where('id_mahasiswa', DB::table('mahasiswa')->where('nim', '241001')->value('id'))->where('id_semester', DB::table('semester')->where('status', 'Aktif')->first()->id)->value('id');
        $kelasMk = DB::table('kelas_mk')->where('kode_kelas_mk', 'IF101-A')->value('id');

        $krsDetails = [
            [
                'id' => (string) Str::uuid(),
                'id_krs' => $krs,
                'id_kelas_mk' => $kelasMk,
                'sks_diambil' => 3, // Jumlah SKS dari kelas_mk
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('krs_detail')->insert($krsDetails);
    }
}
