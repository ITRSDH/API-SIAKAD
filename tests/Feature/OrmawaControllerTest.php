<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Website\Ormawa;
use Illuminate\Http\UploadedFile;

class OrmawaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_ormawa_list()
    {
        Ormawa::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/ormawa');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id', 'nama', 'deskripsi', 'gambar', 'created_at', 'updated_at'
                    ]
                ]
            ]);
    }

    public function test_store_creates_ormawa_with_file()
    {
        $file = UploadedFile::fake()->image('ormawa.jpg');
            $data = [
                'nama' => 'BEM',
                'kategori' => 'akademik',
                'deskripsi' => 'Deskripsi BEM',
                'gambar' => $file,
            ];

        $response = $this->post('/api/v1/ormawa', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Ormawa berhasil ditambahkan',
            ]);

            $this->assertDatabaseHas('ormawa', [
                'nama' => $data['nama'],
                'kategori' => $data['kategori'],
            ]);

    $ormawa = Ormawa::where('nama', $data['nama'])->first();
    $this->assertNotNull($ormawa->gambar);
    }

    public function test_show_returns_ormawa_detail()
    {
        $ormawa = Ormawa::factory()->create();

        $response = $this->getJson('/api/v1/ormawa/' . $ormawa->id);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'success' => true,
                'message' => 'Detail ormawa',
                'id' => $ormawa->id,
                'nama' => $ormawa->nama,
            ]);
    }

    public function test_update_modifies_ormawa_with_file()
    {
        $ormawa = Ormawa::factory()->create();
        $file = UploadedFile::fake()->image('ormawa2.jpg');
            $data = [
                'nama' => 'UKM',
                'kategori' => 'seni',
                'gambar' => $file,
            ];

        $response = $this->put('/api/v1/ormawa/' . $ormawa->id, $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Ormawa berhasil diperbarui',
            ]);

            $this->assertDatabaseHas('ormawa', [
                'id' => $ormawa->id,
                'nama' => $data['nama'],
                'kategori' => $data['kategori'],
            ]);

        $ormawa->refresh();
        $this->assertNotNull($ormawa->gambar);
    }

    public function test_destroy_deletes_ormawa()
    {
        $ormawa = Ormawa::factory()->create();

        $response = $this->deleteJson('/api/v1/ormawa/' . $ormawa->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Ormawa berhasil dihapus',
            ]);

        $this->assertDatabaseMissing('ormawa', [
            'id' => $ormawa->id
        ]);
    }
}
