<?php

namespace Database\Factories\Website;

use App\Models\Website\Prestasi;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PrestasiFactory extends Factory
{
    protected $model = Prestasi::class;

    public function definition()
    {
        $prestasiTitles = [
            'Juara 1 Kompetisi Coding Nasional',
            'Pemenang Hackathon 2024',
            'Beasiswa Penuh Akademik',
            'Juara Debat Internasional',
            'Pemenang Lomba Inovasi Teknologi',
            'Mahasiswa Berprestasi Tahun 2024',
            'Penerima Award Kepemimpinan',
            'Juara Kompetisi UI/UX Design',
            'Pemenang Scholarship Internasional',
            'Penghargaan Best Paper di Konferensi Internasional',
        ];

        return [
            'nama_mahasiswa' => $this->faker->name(),
            'judul_prestasi' => $this->faker->randomElement($prestasiTitles),
            'tingkat' => $this->faker->randomElement(['kampus', 'nasional', 'internasional']),
            'tahun' => $this->faker->numberBetween(2020, 2024),
            'deskripsi' => $this->faker->paragraph(),
        ];
    }
}
