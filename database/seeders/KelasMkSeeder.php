<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KelasMkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mataKuliah = DB::table('mata_kuliah')->where('kode_mk', 'IF101')->value('id');
        $kelasPararelA = DB::table('kelas_pararel')->where('nama_kelas', 'A')->first()->id;
        $semesterAktif = DB::table('semester')->where('status', 'Aktif')->first()->id;
        $jenisKelasReguler = DB::table('jenis_kelas')->where('nama_kelas', 'Reguler')->first()->id; // Sesuaikan dengan data yang ada

        $kelasMks = [
            [
                'id' => (string) Str::uuid(),
                'id_mk' => $mataKuliah,
                'id_kelas_pararel' => $kelasPararelA,
                'id_semester' => $semesterAktif,
                'id_jenis_kelas' => $jenisKelasReguler, // <-- Tambahkan baris ini
                'kode_kelas_mk' => 'IF101-A',
                'kuota' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('kelas_mk')->insert($kelasMks);
    }
}
