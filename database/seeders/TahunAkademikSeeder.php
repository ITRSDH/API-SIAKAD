<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TahunAkademikSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tahunAkademiks = [
            [
                'id' => (string) Str::uuid(),
                'tahun_akademik' => '2023/2024',
                'status_aktif' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'tahun_akademik' => '2024/2025',
                'status_aktif' => true, // Misalnya tahun ini aktif
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'tahun_akademik' => '2025/2026',
                'status_aktif' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('tahun_akademik')->insert($tahunAkademiks);
    }
}
