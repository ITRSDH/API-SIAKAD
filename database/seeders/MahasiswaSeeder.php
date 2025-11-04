<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MahasiswaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $prodiS1Kep = DB::table('prodi')->where('kode_prodi', 'S1-KEP')->value('id');
        $kelasPararelA = DB::table('kelas_pararel')->where('nama_kelas', 'A')->where('id_prodi', $prodiS1Kep)->value('id');
        $dosenWali = DB::table('dosen')->where('nidn', '1234567890')->value('id');

        $mahasiswas = [
            [
                'id' => (string) Str::uuid(),
                'id_prodi' => $prodiS1Kep,
                'id_kelas_pararel' => $kelasPararelA,
                'id_dosen' => $dosenWali,
                'nim' => '241001',
                'nama_mahasiswa' => 'Alice Johnson',
                'jenis_kelamin' => 'P',
                'tanggal_lahir' => '2005-03-10',
                'alamat' => 'Jl. Mahasiswa No. 1',
                'no_hp' => '081357924680',
                'email' => 'alice.j@example.com',
                'asal_sekolah' => 'SMAN 1 Contoh',
                'nama_orang_tua' => 'Bob Johnson',
                'no_hp_orang_tua' => '081111111111',
                'status' => 'Aktif',
                'angkatan' => 2024,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'id_prodi' => $prodiS1Kep,
                'id_kelas_pararel' => $kelasPararelA,
                'id_dosen' => $dosenWali,
                'nim' => '241002',
                'nama_mahasiswa' => 'Bob Williams',
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => '2004-07-18',
                'alamat' => 'Jl. Mahasiswa No. 2',
                'no_hp' => '082468135790',
                'email' => 'bob.w@example.com',
                'asal_sekolah' => 'SMAN 2 Contoh',
                'nama_orang_tua' => 'Charlie Williams',
                'no_hp_orang_tua' => '082222222222',
                'status' => 'Aktif',
                'angkatan' => 2024,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('mahasiswa')->insert($mahasiswas);
    }
}
