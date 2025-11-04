<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Website\Galeri;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

class GaleriControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_galeri_list()
    {
        Galeri::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/galeri');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['id', 'judul', 'kategori', 'gambar', 'deskripsi', 'tanggal', 'created_at']
                ]
            ]);
    }

    public function test_store_creates_galeri()
    {
        $file = UploadedFile::fake()->image('galeri.jpg');
        $data = [
            'judul' => 'Judul Galeri',
            'kategori' => 'kegiatan',
            'gambar' => $file,
            'deskripsi' => 'Deskripsi galeri',
            'tanggal' => now()->toDateString(),
        ];

        $response = $this->post('/api/v1/galeri', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Galeri berhasil ditambahkan',
            ]);

        $this->assertDatabaseHas('galeri', [
            'judul' => $data['judul'],
            'kategori' => $data['kategori'],
            'deskripsi' => $data['deskripsi'],
            'tanggal' => $data['tanggal'],
        ]);

        $galeri = Galeri::where('judul', $data['judul'])->first();
        $this->assertNotNull($galeri->gambar);
    }

    public function test_show_returns_galeri_detail()
    {
        $galeri = Galeri::factory()->create();

        $response = $this->getJson('/api/v1/galeri/' . $galeri->id);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'success' => true,
                'message' => 'Detail galeri',
                'id' => $galeri->id,
                'judul' => $galeri->judul,
                'kategori' => $galeri->kategori,
                'deskripsi' => $galeri->deskripsi,
                'tanggal' => $galeri->tanggal,
            ]);

        // Pastikan gambar adalah string path
        $this->assertIsString($response->json('data.gambar'));
    }

    public function test_update_modifies_galeri()
    {
        $galeri = Galeri::factory()->create();
        $file = UploadedFile::fake()->image('update.jpg');
        $data = [
            'judul' => 'Judul Update',
            'kategori' => 'umum',
            'gambar' => $file,
            'deskripsi' => 'Deskripsi update',
            'tanggal' => now()->toDateString(),
        ];

        $response = $this->put('/api/v1/galeri/' . $galeri->id, $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Galeri berhasil diperbarui',
            ]);

        $this->assertDatabaseHas('galeri', [
            'id' => $galeri->id,
            'judul' => $data['judul'],
            'kategori' => $data['kategori'],
            'deskripsi' => $data['deskripsi'],
            'tanggal' => $data['tanggal'],
        ]);

        $galeri->refresh();
        $this->assertNotNull($galeri->gambar);
    }

    public function test_destroy_deletes_galeri()
    {
        $galeri = Galeri::factory()->create();

        $response = $this->deleteJson('/api/v1/galeri/' . $galeri->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Galeri berhasil dihapus',
            ]);

        $this->assertDatabaseMissing('galeri', [
            'id' => $galeri->id
        ]);
    }
}
