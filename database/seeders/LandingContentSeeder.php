<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Website\LandingContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LandingContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create single landing content record
        LandingContent::create([
            // Hero Section
            'hero_title' => 'STIKES Dian Husada Mojokerto',
            'hero_subtitle' => 'Mencetak Tenaga Kesehatan Profesional yang Berintegritas, Intelektual, dan Berjiwa Entrepreneur',
            'hero_background' => null,

            // Statistik
            'jumlah_program_studi' => 4,
            'jumlah_mahasiswa' => 1500,
            'jumlah_dosen' => 75,
            'jumlah_mitra' => 25,

            // Keunggulan
            'keunggulan' => 'Test',

            // Logo dan nama aplikasi
            'logo' => null,
            'nama_aplikasi' => 'SIAKAD STIKES Dian Husada',

            // Footer
            'deskripsi_footer' => 'Sistem Informasi Akademik STIKES Dian Husada Mojokerto adalah platform digital yang menyediakan layanan akademik terintegrasi untuk mahasiswa, dosen, dan tenaga kependidikan. Dengan teknologi modern, kami memudahkan proses pembelajaran dan administrasi kampus.',
            'facebook' => 'https://facebook.com/stikesdianhusada',
            'twitter' => 'https://twitter.com/stikesdianhusada',
            'instagram' => 'https://instagram.com/stikesdianhusada',
            'linkedin' => 'https://linkedin.com/school/stikesdianhusada',
            'youtube' => 'https://youtube.com/@stikesdianhusada',
            'alamat' => 'Jl. Raya By Pass No. 10, Mojokerto, Jawa Timur 61318, Indonesia',
            'telepon' => '+62 321-123456',
            'email' => 'info@stikesdianhusada.ac.id',
        ]);
    }
}
