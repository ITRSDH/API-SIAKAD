<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KhsDetailSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $khs = DB::table('khs')->where('id_mahasiswa', DB::table('mahasiswa')->where('nim', '241001')->value('id'))->where('id_semester', DB::table('semester')->where('status', 'Aktif')->first()->id)->value('id');
        $mataKuliah = DB::table('mata_kuliah')->where('kode_mk', 'IF101')->value('id');
        $nilai = DB::table('nilai')->where('id_kelas_mk', DB::table('kelas_mk')->where('kode_kelas_mk', 'IF101-A')->value('id'))->where('id_mahasiswa', DB::table('mahasiswa')->where('nim', '241001')->value('id'))->value('id');

        $khsDetails = [
            [
                'id' => (string) Str::uuid(),
                'id_khs' => $khs,
                'id_mk' => $mataKuliah,
                'nilai_huruf' => 'A-',
                'bobot' => 3.70,
                'sks' => 3,
                'id_nilai' => $nilai, // Referensi ke nilai yang diambil
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('khs_detail')->insert($khsDetails);
    }
}
