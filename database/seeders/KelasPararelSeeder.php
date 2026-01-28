<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KelasPararelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $prodiS1Kep = DB::table('prodi')->where('kode_prodi', 'D3-KBD')->value('id');

        $kelasPararels = [
            [
                'id' => (string) Str::uuid(),
                'id_prodi' => $prodiS1Kep,
                'nama_kelas' => 'A',
                'angkatan' => 2024,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'id_prodi' => $prodiS1Kep,
                'nama_kelas' => 'B',
                'angkatan' => 2024,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('kelas_pararel')->insert($kelasPararels);
    }
}
