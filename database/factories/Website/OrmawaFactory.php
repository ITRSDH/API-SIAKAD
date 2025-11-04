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
        return [
            'id' => (string) Str::uuid(),
            'nama' => $this->faker->company(),
            'kategori' => $this->faker->randomElement(['akademik', 'seni', 'olahraga', 'sosial']),
            'deskripsi' => $this->faker->paragraph(),
            'gambar' => 'ormawa/' . $this->faker->uuid . '.jpg',
            // 'visi' and 'misi' removed to match migration
            // struktur_organisasi and kontak removed to match migration
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
