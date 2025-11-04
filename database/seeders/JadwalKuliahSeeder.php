<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JadwalKuliahSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kelasMk = DB::table('kelas_mk')->where('kode_kelas_mk', 'IF101-A')->value('id');
        $dosen = DB::table('dosen')->where('nidn', '1234567890')->value('id');
        $ruang = DB::table('ruang')->where('nama_ruang', 'Ruang A101')->value('id');
        $semesterAktif = DB::table('semester')->where('status', 'Aktif')->first()->id;

        $jadwalKuliahs = [
            [
                'id' => (string) Str::uuid(),
                'id_kelas_mk' => $kelasMk,
                'id_dosen' => $dosen,
                'id_ruang' => $ruang,
                'id_semester' => $semesterAktif,
                'hari' => 'Senin',
                'jam_mulai' => '08:00:00',
                'jam_selesai' => '10:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('jadwal_kuliah')->insert($jadwalKuliahs);
    }
}
