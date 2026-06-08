<?php

namespace Database\Seeders;

use App\Models\MasterData\RefJenisKurikulum;
use Illuminate\Database\Seeder;

class RefJenisKurikulumSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'kode_jenis' => 'INTI',
                'nama_jenis_kurikulum' => 'Kurikulum Inti (Nasional)',
                'is_aktif' => true,
            ],
            [
                'kode_jenis' => 'INST',
                'nama_jenis_kurikulum' => 'Kurikulum Institusi',
                'is_aktif' => true,
            ],
            [
                'kode_jenis' => 'KBK',
                'nama_jenis_kurikulum' => 'Kurikulum Berbasis Kompetensi (KBK)',
                'is_aktif' => true,
            ],
            [
                'kode_jenis' => 'MBKM',
                'nama_jenis_kurikulum' => 'Kurikulum Merdeka Belajar Kampus Merdeka',
                'is_aktif' => true,
            ],
            [
                'kode_jenis' => 'OBE',
                'nama_jenis_kurikulum' => 'Outcome-Based Education (OBE)',
                'is_aktif' => true,
            ],
        ];

        foreach ($data as $item) {
            RefJenisKurikulum::updateOrCreate(
                ['kode_jenis' => $item['kode_jenis']],
                $item
            );
        }
    }
}
