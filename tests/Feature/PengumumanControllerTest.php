<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Website\Pengumuman;

class PengumumanControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_pengumuman_list()
    {
        Pengumuman::factory()->count(2)->create();
        $response = $this->getJson('/api/v1/pengumuman');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success', 'message', 'data' => [['id', 'judul', 'isi', 'kategori', 'created_at']]
            ]);
    }

    public function test_store_creates_pengumuman()
    {
        $data = [
            'judul' => 'Test Judul',
            'isi' => 'Test Isi',
            'kategori' => 'Info',
        ];
        $response = $this->postJson('/api/v1/pengumuman', $data);
        $response->assertStatus(201)
            ->assertJson(['success' => true]);
        $this->assertDatabaseHas('pengumuman', ['judul' => 'Test Judul']);
    }

    public function test_show_returns_pengumuman_detail()
    {
        $pengumuman = Pengumuman::factory()->create();
        $response = $this->getJson('/api/v1/pengumuman/' . $pengumuman->id);
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_update_modifies_pengumuman()
    {
        $pengumuman = Pengumuman::factory()->create();
        $data = ['judul' => 'Updated Judul'];
        $response = $this->putJson('/api/v1/pengumuman/' . $pengumuman->id, $data);
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        $this->assertDatabaseHas('pengumuman', ['id' => $pengumuman->id, 'judul' => 'Updated Judul']);
    }

    public function test_destroy_deletes_pengumuman()
    {
        $pengumuman = Pengumuman::factory()->create();
        $response = $this->deleteJson('/api/v1/pengumuman/' . $pengumuman->id);
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        $this->assertDatabaseMissing('pengumuman', ['id' => $pengumuman->id]);
    }
}
