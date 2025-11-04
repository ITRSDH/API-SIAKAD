<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PresensiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kelasMk = DB::table('kelas_mk')->where('kode_kelas_mk', 'IF101-A')->value('id');
        $mahasiswa = DB::table('mahasiswa')->where('nim', '241001')->value('id');

        $presensis = [
            [
                'id' => (string) Str::uuid(),
                'id_kelas_mk' => $kelasMk,
                'id_mahasiswa' => $mahasiswa,
                'tanggal' => now()->subDays(1)->toDateString(), // Kemarin
                'status_hadir' => 'Hadir',
                'keterangan' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'id_kelas_mk' => $kelasMk,
                'id_mahasiswa' => $mahasiswa,
                'tanggal' => now()->toDateString(), // Hari ini
                'status_hadir' => 'Sakit',
                'keterangan' => 'Izin sakit dengan surat dokter.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('presensi')->insert($presensis);
    }
}
