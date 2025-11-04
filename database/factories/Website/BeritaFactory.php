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
        return [
            'id' => (string) Str::uuid(),
            'judul' => $this->faker->sentence(6),
            'isi' => $this->faker->paragraph(3),
            'kategori' => $this->faker->randomElement(['umum', 'pengumuman', 'kegiatan']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
