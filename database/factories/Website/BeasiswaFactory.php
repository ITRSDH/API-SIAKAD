<?php

namespace Database\Factories\Website;

use App\Models\Website\Beasiswa;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BeasiswaFactory extends Factory
{
    protected $model = Beasiswa::class;

    public function definition()
    {
        $namaBeasiswa = [
            'Beasiswa Prestasi Akademik',
            'Beasiswa Bidik Misi',
            'Beasiswa KIP Kuliah',
            'Beasiswa Unggulan',
            'Beasiswa PPA (Peningkatan Prestasi Akademik)',
            'Beasiswa BBM (Bantuan Belajar Mahasiswa)',
            'Beasiswa Supersemar',
            'Beasiswa Djarum',
            'Beasiswa Bank Indonesia',
            'Beasiswa Tanoto Foundation',
            'Beasiswa LPDP',
            'Beasiswa Yayasan Karya Salemba Empat',
        ];

        $kategoriList = ['Akademik', 'Non-Akademik', 'Prestasi', 'Ekonomi', 'Riset'];

        return [
            'nama' => $this->faker->randomElement($namaBeasiswa) . ' ' . $this->faker->numberBetween(2024, 2025),
            'kategori' => $this->faker->randomElement($kategoriList),
            'deskripsi' => $this->faker->paragraph(3),
            'gambar' => null, // Will be uploaded manually
            'deadline' => $this->faker->dateTimeBetween('now', '+6 months'),
            'kuota' => $this->faker->numberBetween(10, 100),
        ];
    }
}
