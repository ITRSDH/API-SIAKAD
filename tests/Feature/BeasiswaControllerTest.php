<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Website\Beasiswa;
use Illuminate\Http\UploadedFile;

class BeasiswaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_beasiswa_list()
    {
        Beasiswa::factory()->count(2)->create();
        $response = $this->getJson('/api/v1/beasiswa');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success', 'message', 'data' => [[
                    'id', 'nama', 'kategori', 'deskripsi', 'gambar', 'deadline', 'kuota', 'created_at'
                ]]
            ]);
    }

    public function test_store_creates_beasiswa()
    {
        $file = UploadedFile::fake()->image('beasiswa.jpg');
        $data = [
            'nama' => 'Beasiswa Unggulan',
            'kategori' => 'Akademik',
            'deskripsi' => 'Beasiswa untuk mahasiswa berprestasi',
            'gambar' => $file,
            'deadline' => '2025-12-31',
            'kuota' => 10,
        ];
        $response = $this->post('/api/v1/beasiswa', $data);
        $response->assertStatus(201)
            ->assertJson(['success' => true]);
        $this->assertDatabaseHas('beasiswa', ['nama' => 'Beasiswa Unggulan', 'kategori' => 'Akademik']);
        $beasiswa = Beasiswa::where('nama', $data['nama'])->first();
        $this->assertNotNull($beasiswa->gambar);
    }

    public function test_show_returns_beasiswa_detail()
    {
        $beasiswa = Beasiswa::factory()->create();
        $response = $this->getJson('/api/v1/beasiswa/' . $beasiswa->id);
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_update_modifies_beasiswa()
    {
        $beasiswa = Beasiswa::factory()->create();
        $file = UploadedFile::fake()->image('beasiswa-update.jpg');
        $data = [
            'nama' => 'Beasiswa Update',
            'gambar' => $file,
        ];
        $response = $this->put('/api/v1/beasiswa/' . $beasiswa->id, $data);
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        $this->assertDatabaseHas('beasiswa', ['id' => $beasiswa->id, 'nama' => 'Beasiswa Update']);
        $beasiswa->refresh();
        $this->assertNotNull($beasiswa->gambar);
    }

    public function test_destroy_deletes_beasiswa()
    {
        $beasiswa = Beasiswa::factory()->create();
        $response = $this->deleteJson('/api/v1/beasiswa/' . $beasiswa->id);
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        $this->assertDatabaseMissing('beasiswa', ['id' => $beasiswa->id]);
    }
}
