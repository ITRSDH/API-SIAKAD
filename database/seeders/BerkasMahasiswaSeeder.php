<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BerkasMahasiswaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mahasiswa = DB::table('mahasiswa')->where('nim', '241001')->value('id');

        $berkas = [
            [
                'id' => (string) Str::uuid(),
                'id_mahasiswa' => $mahasiswa,
                'jenis_berkas' => 'KRS',
                'file_path' => 'berkas/krs/241001_krs_semester_ganjil_2024_2025.pdf',
                'file_nama' => 'KRS_Alice_Johnson_Semester_Ganjil_2024_2025.pdf',
                'tanggal_upload' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('berkas_mahasiswa')->insert($berkas);
    }
}
