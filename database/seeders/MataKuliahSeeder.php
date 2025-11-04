<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MataKuliahSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kurikulumAktif = DB::table('kurikulum')->where('status', true)->where('id_prodi', DB::table('prodi')->where('kode_prodi', 'S1-KEP')->value('id'))->value('id');

        $mataKuliahs = [
            [
                'id' => (string) Str::uuid(),
                'id_kurikulum' => $kurikulumAktif,
                'kode_mk' => 'IF101',
                'nama_mk' => 'Ilmu Keperawatan Dasar',
                'sks' => 3,
                'semester_rekomendasi' => 1,
                'jenis' => 'Wajib',
                'deskripsi' => 'Mata kuliah dasar keperawatan.',
                'teori' => 2,
                'seminar' => 1,
                'praktikum' => 1,
                'praktek_klinik' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'id_kurikulum' => $kurikulumAktif,
                'kode_mk' => 'IF202',
                'nama_mk' => 'Asuhan Keperawatan Anak',
                'sks' => 4,
                'semester_rekomendasi' => 4,
                'jenis' => 'Wajib',
                'deskripsi' => 'Mata kuliah tentang asuhan keperawatan pada anak.',
                'teori' => 2,
                'seminar' => 1,
                'praktikum' => 1,
                'praktek_klinik' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'id_kurikulum' => $kurikulumAktif,
                'kode_mk' => 'IF303',
                'nama_mk' => 'Keperawatan Jiwa',
                'sks' => 3,
                'semester_rekomendasi' => 6,
                'jenis' => 'Pilihan',
                'deskripsi' => 'Mata kuliah tentang keperawatan pada pasien gangguan jiwa.',
                'teori' => 2,
                'seminar' => 0,
                'praktikum' => 0,
                'praktek_klinik' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('mata_kuliah')->insert($mataKuliahs);
    }
}
