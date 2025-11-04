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
        return [
            'id' => (string) Str::uuid(),
            'nama_mahasiswa' => $this->faker->name(),
            'program_studi' => $this->faker->randomElement(['Teknik Informatika', 'Sistem Informasi', 'Manajemen']),
            'judul_prestasi' => $this->faker->sentence(3),
            'tingkat' => $this->faker->randomElement(['kampus', 'nasional', 'internasional']),
            'tahun' => $this->faker->year(),
            'deskripsi' => $this->faker->optional()->paragraph(),
            'gambar' => $this->faker->optional()->imageUrl(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
