<?php

namespace Database\Factories\Website;

use App\Models\Website\Galeri;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GaleriFactory extends Factory
{
    protected $model = Galeri::class;

    public function definition()
    {
        return [
            'id' => (string) Str::uuid(),
            'judul' => $this->faker->sentence(3),
            'kategori' => $this->faker->randomElement(['kegiatan', 'umum', 'acara']),
            'gambar' => $this->faker->imageUrl(800, 600, 'nature', true, 'galeri'),
            'deskripsi' => $this->faker->paragraph(),
            'tanggal' => $this->faker->date(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
