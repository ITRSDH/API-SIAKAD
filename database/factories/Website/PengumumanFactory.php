<?php

namespace Database\Factories\Website;

use App\Models\Website\Pengumuman;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PengumumanFactory extends Factory
{
    protected $model = Pengumuman::class;

    public function definition()
    {
        $judulPengumuman = [
            'Pengumuman Libur Semester Genap',
            'Jadwal Ujian Akhir Semester',
            'Pendaftaran Kuliah Kerja Nyata (KKN)',
            'Pembayaran UKT Semester Gasal',
            'Seleksi Beasiswa Prestasi',
            'Wisuda Periode Agustus 2024',
            'Pengumuman Pemenang Lomba',
            'Kegiatan Dies Natalis Kampus',
            'Rekrutmen Asisten Dosen',
            'Lomba Karya Tulis Ilmiah',
            'Seminar Nasional Teknologi Informasi',
            'Pendaftaran Program Magang',
        ];

        $kategoriList = ['Info', 'Penting', 'Umum', 'Akademik', 'Kemahasiswaan'];

        return [
            'judul' => $this->faker->randomElement($judulPengumuman),
            'isi' => $this->faker->paragraphs(3, true),
            'kategori' => $this->faker->randomElement($kategoriList),
            'tanggal' => $this->faker->dateTimeBetween('-1 month', '+1 month'),
        ];
    }
}
