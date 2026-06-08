<?php

namespace Database\Seeders;

use App\Models\MasterData\Kurikulum;
use App\Models\MasterData\KurikulumInduk;
use App\Models\MasterData\RefJenisKurikulum;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KurikulumSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $prodiD3KBD = DB::table('prodi')->where('kode_prodi', 'D3-KBD')->value('id');
        if (!$prodiD3KBD) {
            return;
        }

        $jenisInstitusi = RefJenisKurikulum::query()->where('kode_jenis', 'INST')->first();
        if (!$jenisInstitusi) {
            return;
        }

        $induk2020 = KurikulumInduk::updateOrCreate(
            [
                'id_prodi' => $prodiD3KBD,
                'id_jenis_kurikulum' => $jenisInstitusi->id,
                'tahun_kurikulum' => '2020',
            ],
            [
                'nama_kurikulum' => '2020 - ' . $jenisInstitusi->nama_jenis_kurikulum,
                'kode_kurikulum' => '2020-INST-D3KBD',
                'is_aktif' => true,
            ]
        );

        $induk2015 = KurikulumInduk::updateOrCreate(
            [
                'id_prodi' => $prodiD3KBD,
                'id_jenis_kurikulum' => $jenisInstitusi->id,
                'tahun_kurikulum' => '2015',
            ],
            [
                'nama_kurikulum' => '2015 - ' . $jenisInstitusi->nama_jenis_kurikulum,
                'kode_kurikulum' => '2015-INST-D3KBD',
                'is_aktif' => false,
            ]
        );

        $semesterId = DB::table('semester')->value('id');
        if (!$semesterId) {
            return;
        }

        Kurikulum::updateOrCreate(
            [
                'id_prodi' => $prodiD3KBD,
                'id_kurikulum_induk' => $induk2020->id,
                'nama_struktur_mk' => 'Struktur Operasional 2020',
            ],
            [
                'id_semester' => $semesterId,
                'jumlah_sks_wajib' => 110,
                'jumlah_sks_pilihan' => 34,
                'jumlah_sks_lulus' => 144,
            ]
        );

        Kurikulum::updateOrCreate(
            [
                'id_prodi' => $prodiD3KBD,
                'id_kurikulum_induk' => $induk2015->id,
                'nama_struktur_mk' => 'Struktur Operasional 2015',
            ],
            [
                'id_semester' => $semesterId,
                'jumlah_sks_wajib' => 108,
                'jumlah_sks_pilihan' => 36,
                'jumlah_sks_lulus' => 144,
            ]
        );
    }
}
