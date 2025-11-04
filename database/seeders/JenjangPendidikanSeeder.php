<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class JenjangPendidikanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jenjangs = [
            [
                'id' => (string) Str::uuid(),
                'kode_jenjang' => 'D3',
                'nama_jenjang' => 'Diploma III (D3)',
                'deskripsi' => 'Jenjang pendidikan Diploma Tiga.',
                'jumlah_semester' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'kode_jenjang' => 'S1',
                'nama_jenjang' => 'Sarjana (S1)',
                'deskripsi' => 'Jenjang pendidikan Sarjana.',
                'jumlah_semester' => 8,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'kode_jenjang' => 'Ners',
                'nama_jenjang' => 'Profesi Ners',
                'deskripsi' => 'Jenjang pendidikan Profesi Ners setelah S1 Keperawatan.',
                'jumlah_semester' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'kode_jenjang' => 'S2',
                'nama_jenjang' => 'Magister (S2)',
                'deskripsi' => 'Jenjang pendidikan Magister.',
                'jumlah_semester' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('jenjang_pendidikan')->insert($jenjangs);
    }
}
