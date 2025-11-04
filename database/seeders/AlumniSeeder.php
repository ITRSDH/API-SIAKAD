<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AlumniSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Misalnya, kita hanya membuat data alumni jika ada mahasiswa yang statusnya Lulus
        // Untuk contoh, kita buat satu entri manual
        $mahasiswaLulus = DB::table('mahasiswa')->where('status', 'Lulus')->first();
        if ($mahasiswaLulus) {
            $alumni = [
                [
                    'id' => (string) Str::uuid(),
                    'id_mahasiswa' => $mahasiswaLulus->id,
                    'tanggal_lulus' => now()->subMonths(6)->toDateString(), // Lulus 6 bulan lalu
                    'ipk' => 3.85,
                    'no_ijazah' => 'IJZ-2025-001',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ];

            DB::table('alumni')->insert($alumni);
        }
        // Jika tidak ada mahasiswa lulus, seeder ini tidak akan menambahkan data apapun.
        // Untuk pengujian, kamu bisa ubah status salah satu mahasiswa ke 'Lulus' terlebih dahulu.
    }
}
