<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JenisKelasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jenisKelas = [
            [
                'id' => (string) Str::uuid(),
                'nama_kelas' => 'Reguler',
                'deskripsi' => 'Kelas reguler pagi.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'nama_kelas' => 'Karyawan',
                'deskripsi' => 'Kelas malam untuk karyawan.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'nama_kelas' => 'Online',
                'deskripsi' => 'Kelas daring.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('jenis_kelas')->insert($jenisKelas);
    }
}
