<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Website\ProfileKampus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProfileKampusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create single profile kampus record
        ProfileKampus::create([
            'judul' => 'Profil STIKES DIAN HUSADA',
            'deskripsi' => 'STIKES Dian Husada Mojokerto merupakan perguruan tinggi swasta di bidang kesehatan yang berada di bawah naungan Yayasan Lembaga Pendidikan Dian Husada. Berdiri berdasarkan Keputusan Menteri Pendidikan Nasional RI Nomor 124/D/O/2006 tanggal 18 Juli 2006, STIKES Dian Husada mulai menyelenggarakan pendidikan pada tahun akademik 2006/2007 dengan Program Studi D3 Kebidanan dan Ilmu Keperawatan.
            Seiring perkembangan, pada tahun 2012 STIKES Dian Husada memperoleh izin penyelenggaraan Program Studi Profesi Ners. Selanjutnya, pada tahun 2025 dibuka Program Studi Teknologi Radiologi Pencitraan Program Sarjana Terapan berdasarkan izin Kementerian Pendidikan Tinggi, Sains, dan Teknologi RI.
            STIKES Dian Husada Mojokerto berkomitmen menghasilkan tenaga kesehatan profesional yang berintegritas, berwawasan intelektual, dan berjiwa entrepreneur. Dies Natalis STIKES Dian Husada diperingati setiap tanggal 18 Juli.',
            'visi' => 'Institusi Kesehatan Yang Unggul Dalam Integritas, Intelektual, Dan Berjiwa Enterpreuneur Pada Tahun 2030',
            'misi' => '1. Menyelenggarakan pendidikan berkualitas tinggi dalam bidang teknologi informasi.\n2. Mengembangkan penelitian yang inovatif dan relevan dengan kebutuhan industri.\n3. Membangun kerjasama strategis dengan industri dan institusi terkait.\n4. Menumbuhkembangkan jiwa kewirausahaan dan soft skills mahasiswa.\n5. Berkontribusi aktif dalam pengembangan masyarakat melalui teknologi.',
            'struktur_image' => null,
            'fasilitas' => '1. R. Laboratorium Anak Sehat
                2. R. Laboratorium Anak Sakit
                3. R. Laboratorium Neonatus
                4. R. Laboratorium Maternitas ANC
                5. R. Laboratorium Maternitas INC
                6. R. Laboratorium Maternitas PNC
                7. R. Laboratorium Maternitas KB
                8. R. Laboratorium Keperawatan MediKal Bedah
                9. R. Laboratorium Gawat Darurat
                10. R. Laboratorium Ponek
                11. R. Laboratorium Enterpreneur
                12. R. Laboratorium Komunitas dan Keluarga
                13. R. Laboratorium Gerontik
                14. R. Laboratorium Jiwa
                15. R. Laboratorium Anatomi
                16. R. Laboratorium Proteksi Radiasi Dan Fisika Radiasi
                17. R. Laboratorium Quality Control
                18. R. Laboratorium Ultrasonografi (USG)
                19. R. Laboratorium Radiofotografi
                20. R. Laboratorium Radiografi Imaging
                21. R. Laboratorium Imaging Dan Computer Radiologi
                22. R. Laboratorium CT Scan
                23. R. Laboratorium MRI
                24. Inovatif, Riset dan Pengembangan
                25. OSCE Central',
        ]);
    }
}
