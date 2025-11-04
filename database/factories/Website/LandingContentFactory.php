<?php

namespace Database\Factories\Website;

use App\Models\Website\LandingContent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LandingContentFactory extends Factory
{
    protected $model = LandingContent::class;

    public function definition()
    {
        return [
            'id' => (string) Str::uuid(),
            'hero_title' => $this->faker->sentence(3),
            'hero_subtitle' => $this->faker->sentence(5),
            'hero_background' => 'landing/hero/' . $this->faker->uuid . '.jpg',
            'jumlah_program_studi' => $this->faker->numberBetween(1, 20),
            'jumlah_mahasiswa' => $this->faker->numberBetween(100, 5000),
            'jumlah_dosen' => $this->faker->numberBetween(10, 200),
            'jumlah_mitra' => $this->faker->numberBetween(1, 50),
            'keunggulan' => $this->faker->sentence(6),
            'logo' => 'landing/logo/' . $this->faker->uuid . '.png',
            'nama_aplikasi' => $this->faker->word(),
            'deskripsi_footer' => $this->faker->sentence(8),
            'facebook' => $this->faker->userName(),
            'twitter' => $this->faker->userName(),
            'instagram' => $this->faker->userName(),
            'linkedin' => $this->faker->userName(),
            'youtube' => $this->faker->userName(),
            'alamat' => $this->faker->address(),
            'telepon' => $this->faker->phoneNumber(),
            'email' => $this->faker->safeEmail(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
