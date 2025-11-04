<?php

namespace Database\Factories\Website;

use App\Models\Website\ProfileKampus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProfileKampusFactory extends Factory
{
    protected $model = ProfileKampus::class;

    public function definition()
    {
        return [
            'id' => (string) Str::uuid(),
            'judul' => 'Profil Kampus',
            'deskripsi' => $this->faker->paragraph(),
            'visi' => $this->faker->sentence(4),
            'misi' => $this->faker->sentence(6),
            'struktur_image' => 'profile_kampus/' . $this->faker->uuid . '.jpg',
            'fasilitas' => $this->faker->paragraph(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
