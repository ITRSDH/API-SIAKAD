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
        return [
            'id' => (string) Str::uuid(),
            'judul' => $this->faker->sentence(3),
            'isi' => $this->faker->paragraph(),
            'kategori' => $this->faker->randomElement(['Info', 'Penting', 'Umum']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
