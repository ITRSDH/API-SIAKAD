<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DosenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $prodiS1Kep = DB::table('prodi')->where('kode_prodi', 'S1-KEP')->value('id');

        $dosens = [
            [
                'id' => (string) Str::uuid(),
                'id_prodi' => $prodiS1Kep,
                'nidn' => '1234567890',
                'nup' => 'NUP001',
                'nama_dosen' => 'Dr. John Doe, S.Kep., M.Kep.',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '1980-05-15',
                'alamat' => 'Jl. Contoh No. 123',
                'no_hp' => '081234567890',
                'email' => 'johndoe@example.com',
                'jabatan_akademik' => 'Lektor',
                'pangkat_golongan' => 'III/d',
                'status_aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'id_prodi' => $prodiS1Kep,
                'nidn' => '0987654321',
                'nup' => 'NUP002',
                'nama_dosen' => 'Dr. Jane Smith, S.Kep., M.Kep.',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '1985-08-22',
                'alamat' => 'Jl. Contoh No. 456',
                'no_hp' => '082345678901',
                'email' => 'janesmith@example.com',
                'jabatan_akademik' => 'Asisten Ahli',
                'pangkat_golongan' => 'III/c',
                'status_aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('dosen')->insert($dosens);
    }
}
