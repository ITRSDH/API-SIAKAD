<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Website\Prestasi;
use Illuminate\Http\UploadedFile;

class PrestasiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_prestasi_list()
    {
        Prestasi::factory()->count(2)->create();
        $response = $this->getJson('/api/v1/prestasi');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success', 'message', 'data' => [['id', 'nama_mahasiswa', 'program_studi', 'judul_prestasi', 'tingkat', 'tahun', 'deskripsi', 'gambar', 'created_at']]
            ]);
    }

    public function test_store_creates_prestasi()
    {
        $file = UploadedFile::fake()->image('gambar.jpg');
        $data = [
            'nama_mahasiswa' => 'Budi',
            'program_studi' => 'Teknik Informatika',
            'judul_prestasi' => 'Juara 1 Lomba Coding',
            'tingkat' => 'Nasional',
            'tahun' => 2025,
            'deskripsi' => 'Deskripsi prestasi',
            'gambar' => $file,
        ];
        $response = $this->post('/api/v1/prestasi', $data);
        $response->assertStatus(201)
            ->assertJson(['success' => true]);
        $this->assertDatabaseHas('prestasi', ['nama_mahasiswa' => 'Budi', 'judul_prestasi' => 'Juara 1 Lomba Coding']);
    }

    public function test_show_returns_prestasi_detail()
    {
        $prestasi = Prestasi::factory()->create();
        $response = $this->getJson('/api/v1/prestasi/' . $prestasi->id);
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_update_modifies_prestasi()
    {
        $prestasi = Prestasi::factory()->create();
        $file = UploadedFile::fake()->image('gambar-update.jpg');
        $data = [
            'judul_prestasi' => 'Juara 2 Lomba Coding',
            'gambar' => $file,
        ];
        $response = $this->put('/api/v1/prestasi/' . $prestasi->id, $data);
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        $this->assertDatabaseHas('prestasi', ['id' => $prestasi->id, 'judul_prestasi' => 'Juara 2 Lomba Coding']);
    }

    public function test_destroy_deletes_prestasi()
    {
        $prestasi = Prestasi::factory()->create();
        $response = $this->deleteJson('/api/v1/prestasi/' . $prestasi->id);
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        $this->assertDatabaseMissing('prestasi', ['id' => $prestasi->id]);
    }
}
