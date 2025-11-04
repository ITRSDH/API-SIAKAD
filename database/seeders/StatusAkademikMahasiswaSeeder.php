<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StatusAkademikMahasiswaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mahasiswa = DB::table('mahasiswa')->where('nim', '241001')->value('id');

        $statusAkademiks = [
            [
                'id' => (string) Str::uuid(),
                'id_mahasiswa' => $mahasiswa,
                'status_baru' => 'Aktif',
                'tanggal_ubah' => now()->toDateString(),
                'keterangan' => 'Status diperbarui untuk semester aktif.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('status_akademik_mahasiswa')->insert($statusAkademiks);
    }
}
