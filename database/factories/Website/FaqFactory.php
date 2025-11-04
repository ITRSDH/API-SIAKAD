<?php

namespace Database\Factories\Website;

use App\Models\Website\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FaqFactory extends Factory
{
    protected $model = Faq::class;

    public function definition()
    {
        return [
            'id' => (string) Str::uuid(),
            'pertanyaan' => $this->faker->sentence(8),
            'jawaban' => $this->faker->paragraph(2),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
