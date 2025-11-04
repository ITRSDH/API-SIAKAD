<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RuangSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ruangs = [
            [
                'id' => (string) Str::uuid(),
                'nama_ruang' => 'Ruang A101',
                'kapasitas' => 30,
                'jenis_ruang' => 'Teori',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'nama_ruang' => 'Ruang B202',
                'kapasitas' => 40,
                'jenis_ruang' => 'Teori',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'nama_ruang' => 'Lab. Keperawatan',
                'kapasitas' => 20,
                'jenis_ruang' => 'Praktikum',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'nama_ruang' => 'Lab. Kebidanan',
                'kapasitas' => 15,
                'jenis_ruang' => 'Praktikum',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('ruang')->insert($ruangs);
    }
}
