<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PerwalianSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mahasiswa = DB::table('mahasiswa')->where('nim', '241001')->value('id');
        $dosen = DB::table('dosen')->where('nidn', '1234567890')->value('id');

        $perwalians = [
            [
                'id' => (string) Str::uuid(),
                'id_mahasiswa' => $mahasiswa,
                'id_dosen' => $dosen,
                'tanggal_perwalian' => now()->toDateString(),
                'status_perwalian' => 'Disetujui',
                'keterangan' => 'Perwalian untuk KRS semester ini.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('perwalian')->insert($perwalians);
    }
}
