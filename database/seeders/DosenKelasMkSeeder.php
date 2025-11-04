<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DosenKelasMkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kelasMk = DB::table('kelas_mk')->where('kode_kelas_mk', 'IF101-A')->value('id');
        $dosen = DB::table('dosen')->where('nidn', '1234567890')->value('id');

        $dosenKelasMks = [
            [
                'id' => (string) Str::uuid(),
                'id_kelas_mk' => $kelasMk,
                'id_dosen' => $dosen,
                'peran' => 'Koordinator',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('dosen_kelas_mk')->insert($dosenKelasMks);
    }
}
