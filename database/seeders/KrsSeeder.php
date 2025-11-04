<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KrsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mahasiswa = DB::table('mahasiswa')->where('nim', '241001')->value('id');
        $semesterAktif = DB::table('semester')->where('status', 'Aktif')->first()->id;

        $krs = [
            [
                'id' => (string) Str::uuid(),
                'id_mahasiswa' => $mahasiswa,
                'id_semester' => $semesterAktif,
                'tanggal_pengisian' => now()->toDateString(),
                'status' => 'Disetujui',
                'jumlah_sks_diambil' => 18, // Misalnya KRS ini mengambil 18 SKS
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('krs')->insert($krs);
    }
}
