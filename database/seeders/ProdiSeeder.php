<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\MasterData\Prodi;
use App\Models\MasterData\JenjangPendidikan; // Pastikan model ini juga ada
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProdiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ambil ID Jenjang Pendidikan
        // $d3Id = DB::table('jenjang_pendidikan')->where('kode_jenjang', 'D3')->value('id');
        // $s1Id = DB::table('jenjang_pendidikan')->where('kode_jenjang', 'S1')->value('id');
        // $nersId = DB::table('jenjang_pendidikan')->where('kode_jenjang', 'Ners')->value('id');

        $prodis = [
            [
                'id' => (string) Str::uuid(),
                'jenjang_pendidikan' => 'D3',
                'nama_prodi' => 'Keperawatan',
                'kode_prodi' => 'D3-KEP',
                'akreditasi' => 'Unggul',
                'tahun_berdiri' => 2000, // Contoh tahun
                'gelar_lulusan' => 'Ahli Madya Keperawatan',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'jenjang_pendidikan' => 'D3',
                'nama_prodi' => 'Kebidanan',
                'kode_prodi' => 'D3-KBD',
                'akreditasi' => 'Baik Sekali',
                'tahun_berdiri' => 2005, // Contoh tahun
                'gelar_lulusan' => 'Ahli Madya Kebidanan',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'jenjang_pendidikan' => 'S1',
                'nama_prodi' => 'Sarjana Keperawatan',
                'kode_prodi' => 'S1-KEP',
                'akreditasi' => 'Unggul',
                'tahun_berdiri' => 2010, // Contoh tahun                
                'gelar_lulusan' => 'Sarjana Keperawatan',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'jenjang_pendidikan' => 'Ners',
                'nama_prodi' => 'Profesi Ners',
                'kode_prodi' => 'NERS',
                'akreditasi' => 'Unggul',
                'tahun_berdiri' => 2015, // Contoh tahun                
                'gelar_lulusan' => 'Ners',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('prodi')->insert($prodis);
    }
}
