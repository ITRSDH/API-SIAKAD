<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Website\Faq;
use Illuminate\Support\Str;

class FaqControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_faq_list()
    {
        Faq::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/faq');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['id', 'pertanyaan', 'jawaban', 'created_at']
                ]
            ]);
    }

    public function test_store_creates_faq()
    {
        $data = [
            'pertanyaan' => 'Apa itu SIAKAD?',
            'jawaban' => 'Sistem Informasi Akademik.',
        ];

        $response = $this->postJson('/api/v1/faq', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'FAQ berhasil ditambahkan',
            ]);

        $this->assertDatabaseHas('faq', [
            'pertanyaan' => $data['pertanyaan'],
            'jawaban' => $data['jawaban'],
        ]);
    }

    public function test_show_returns_faq_detail()
    {
        $faq = Faq::factory()->create();

        $response = $this->getJson('/api/v1/faq/' . $faq->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Detail FAQ',
                'data' => [
                    'id' => $faq->id,
                    'pertanyaan' => $faq->pertanyaan,
                    'jawaban' => $faq->jawaban,
                ]
            ]);
    }

    public function test_update_modifies_faq()
    {
        $faq = Faq::factory()->create();
        $data = [
            'pertanyaan' => 'Apa itu update?',
            'jawaban' => 'Ini adalah jawaban update.',
        ];

        $response = $this->putJson('/api/v1/faq/' . $faq->id, $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'FAQ berhasil diperbarui',
            ]);

        $this->assertDatabaseHas('faq', [
            'id' => $faq->id,
            'pertanyaan' => $data['pertanyaan'],
            'jawaban' => $data['jawaban'],
        ]);
    }

    public function test_destroy_deletes_faq()
    {
        $faq = Faq::factory()->create();

        $response = $this->deleteJson('/api/v1/faq/' . $faq->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'FAQ berhasil dihapus',
            ]);

        $this->assertDatabaseMissing('faq', [
            'id' => $faq->id
        ]);
    }
}
