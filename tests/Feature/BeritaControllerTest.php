<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Website\Berita;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

class BeritaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_berita_list()
    {
        Berita::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/berita');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['id', 'judul', 'isi', 'kategori', 'created_at']
                ]
            ]);
    }

    public function test_store_creates_berita()
    {
        $file = UploadedFile::fake()->image('berita.jpg');
        $data = [
            'judul' => 'Judul Berita',
            'isi' => 'Isi berita lengkap.',
            'kategori' => 'umum',
            'gambar' => $file,
        ];

        $response = $this->post('/api/v1/berita', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Berita berhasil ditambahkan',
            ]);

        $this->assertDatabaseHas('berita', [
            'judul' => $data['judul'],
            'isi' => $data['isi'],
            'kategori' => $data['kategori'],
        ]);

        $berita = Berita::where('judul', $data['judul'])->first();
        $this->assertNotNull($berita->gambar);
    }

    public function test_show_returns_berita_detail()
    {
        $berita = Berita::factory()->create();

        $response = $this->getJson('/api/v1/berita/' . $berita->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Detail berita',
                'data' => [
                    'id' => $berita->id,
                    'judul' => $berita->judul,
                    'isi' => $berita->isi,
                    'kategori' => $berita->kategori,
                ]
            ]);
    }

    public function test_update_modifies_berita()
    {
        $berita = Berita::factory()->create();
        $file = UploadedFile::fake()->image('berita-update.jpg');
        $data = [
            'judul' => 'Judul Update',
            'isi' => 'Isi update.',
            'kategori' => 'pengumuman',
            'gambar' => $file,
        ];

        $response = $this->put('/api/v1/berita/' . $berita->id, $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Berita berhasil diperbarui',
            ]);

        $this->assertDatabaseHas('berita', [
            'id' => $berita->id,
            'judul' => $data['judul'],
            'isi' => $data['isi'],
            'kategori' => $data['kategori'],
        ]);

        $berita->refresh();
        $this->assertNotNull($berita->gambar);
    }

    public function test_destroy_deletes_berita()
    {
        $berita = Berita::factory()->create();

        $response = $this->deleteJson('/api/v1/berita/' . $berita->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Berita berhasil dihapus',
            ]);

        $this->assertDatabaseMissing('berita', [
            'id' => $berita->id
        ]);
    }
}
