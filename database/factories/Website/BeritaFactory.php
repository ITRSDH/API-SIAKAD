<?php

namespace Database\Factories\Website;

use App\Models\Website\Berita;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BeritaFactory extends Factory
{
    protected $model = Berita::class;

    public function definition()
    {
        $judulBerita = [
            'Kampus Raih Akreditasi Unggul',
            'Mahasiswa Juara Kompetisi Nasional',
            'Kerja Sama dengan Industri Teknologi',
            'Seminar Internasional Machine Learning',
            'Peluncuran Program Studi Baru',
            'Rektor Lantik Dekan Periode 2024-2028',
            'Lomba Inovasi Mahasiswa Dimulai',
            'Kampus Terima Penghargaan dari Kemendikbud',
            'Pendaftaran Mahasiswa Baru Dibuka',
            'Workshop Digital Marketing untuk UMKM',
            'Kegiatan Pengabdian Masyarakat Sukses',
            'Fasilitas Laboratorium Terbaru Diresmikan',
        ];

        $kategoriList = ['umum', 'pengumuman', 'kegiatan', 'prestasi', 'akademik'];

        return [
            'judul' => $this->faker->randomElement($judulBerita),
            'isi' => $this->faker->paragraphs(5, true),
            'kategori' => $this->faker->randomElement($kategoriList),
            'gambar' => null, // Will be uploaded manually
            'tanggal' => $this->faker->dateTimeBetween('-3 months', 'now'),
        ];
    }
}
