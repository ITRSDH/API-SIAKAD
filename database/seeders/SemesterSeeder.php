<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SemesterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tahun2024 = DB::table('tahun_akademik')->where('tahun_akademik', '2024/2025')->value('id');

        $semesters = [
            [
                'id' => (string) Str::uuid(),
                'id_tahun_akademik' => $tahun2024,
                'nama_semester' => 'Ganjil',
                'kode_semester' => 'G24/25',
                'tanggal_mulai' => '2024-08-01',
                'tanggal_selesai' => '2024-12-20',
                'status' => 'Aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'id_tahun_akademik' => $tahun2024,
                'nama_semester' => 'Genap',
                'kode_semester' => 'Gn24/25',
                'tanggal_mulai' => '2025-01-06',
                'tanggal_selesai' => '2025-05-30',
                'status' => 'Akan Datang',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('semester')->insert($semesters);
    }
}
