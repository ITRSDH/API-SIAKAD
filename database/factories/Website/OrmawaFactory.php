<?php

namespace Database\Factories\Website;

use App\Models\Website\Ormawa;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrmawaFactory extends Factory
{
    protected $model = Ormawa::class;

    public function definition()
    {
        $kategoriList = ['akademik', 'seni', 'olahraga', 'sosial'];
        
        $namaOrmawa = [
            'Himpunan Mahasiswa Informatika',
            'Unit Kegiatan Mahasiswa Seni Tari',
            'Persatuan Sepak Bola Kampus',
            'Kelompok Studi Bahasa Inggris',
            'Badan Eksekutif Mahasiswa',
            'Senat Mahasiswa',
            'Komunitas Fotografi',
            'Paduan Suara Kampus',
            'Basket Ball Club',
            'Himpunan Mahasiswa Akuntansi',
            'Tim Robotika',
            'Komunitas Pecinta Alam',
        ];

        return [
            'nama' => $this->faker->randomElement($namaOrmawa) . ' ' . $this->faker->numberBetween(1, 10),
            'kategori' => $this->faker->randomElement($kategoriList),
            'deskripsi' => $this->faker->paragraph(3),
            'gambar' => null, // Will be uploaded manually or via ImageService
        ];
    }
}
