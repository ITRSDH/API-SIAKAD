<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KhsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mahasiswa = DB::table('mahasiswa')->where('nim', '241001')->value('id');
        $semesterAktif = DB::table('semester')->where('status', 'Aktif')->first()->id;

        $khs = [
            [
                'id' => (string) Str::uuid(),
                'id_mahasiswa' => $mahasiswa,
                'id_semester' => $semesterAktif,
                'ip_semester' => 3.70,
                'total_sks_semester' => 18,
                'ip_kumulatif' => 3.70, // Jika ini semester 1
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('khs')->insert($khs);
    }
}
