<?php

namespace Database\Seeders;

use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class KelasMahasiswaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kelasPararelA = DB::table('kelas_pararel')->where('nama_kelas', 'A')->first()->id;
        $mahasiswa = DB::table('mahasiswa')->where('nim', '241001')->value('id');

        $kelasMahasiswas = [
            [
                'id' => (string) Str::uuid(),
                'id_kelas_pararel' => $kelasPararelA,
                'id_mahasiswa' => $mahasiswa,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('kelas_mahasiswa')->insert($kelasMahasiswas);
    }
}
