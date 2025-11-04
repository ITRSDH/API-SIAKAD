<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KurikulumSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $prodiS1Kep = DB::table('prodi')->where('kode_prodi', 'S1-KEP')->value('id');

        $kurikulums = [
            [
                'id' => (string) Str::uuid(),
                'id_prodi' => $prodiS1Kep,
                'nama_kurikulum' => 'Kurikulum 2020',
                'tahun_kurikulum' => 2020,
                'status' => true, // Aktif
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'id_prodi' => $prodiS1Kep,
                'nama_kurikulum' => 'Kurikulum 2015',
                'tahun_kurikulum' => 2015,
                'status' => false, // Tidak Aktif
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('kurikulum')->insert($kurikulums);
    }
}
