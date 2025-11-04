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
        return [
            'id' => (string) Str::uuid(),
            'nama' => $this->faker->words(2, true),
            'kategori' => $this->faker->randomElement(['Akademik', 'Non-Akademik', 'Prestasi']),
            'deskripsi' => $this->faker->paragraph(),
            'gambar' => $this->faker->imageUrl(),
            'deadline' => $this->faker->date(),
            'kuota' => $this->faker->numberBetween(1, 100),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
