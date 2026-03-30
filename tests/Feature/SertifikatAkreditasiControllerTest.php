<?php

namespace Tests\Feature;

use App\Models\Website\SertifikatAkreditasi;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SertifikatAkreditasiControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        Storage::fake('public');

        $imageServiceMock = $this->mock(ImageService::class);
        $imageServiceMock
            ->shouldReceive('convertToWebpAndReplace')
            ->andReturn('sertifikat_akreditasi/sertifikat.webp');

        $imageServiceMock
            ->shouldReceive('deletePublicFileIfExists')
            ->andReturnTrue();
    }

    public function test_index_returns_list(): void
    {
        SertifikatAkreditasi::create([
            'nama' => 'A',
            'deskripsi' => 'D',
            'foto_sertifikat' => 'sertifikat_akreditasi/a.webp',
        ]);

        $response = $this->getJson('/api/v1/sertifikat-akreditasi');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Daftar sertifikat akreditasi',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['id', 'nama', 'deskripsi', 'foto_sertifikat', 'created_at', 'updated_at'],
                ],
            ]);
    }

    public function test_store_creates_record_with_file(): void
    {
        $file = UploadedFile::fake()->image('sertifikat.jpg');

        $payload = [
            'nama' => 'Akreditasi Institusi',
            'deskripsi' => 'Deskripsi',
            'foto_sertifikat' => $file,
        ];

        $response = $this->post('/api/v1/sertifikat-akreditasi', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Sertifikat akreditasi berhasil ditambahkan',
            ]);

        $this->assertDatabaseHas('sertifikat_akreditasi', [
            'nama' => $payload['nama'],
            'deskripsi' => $payload['deskripsi'],
            'foto_sertifikat' => 'sertifikat_akreditasi/sertifikat.webp',
        ]);
    }

    public function test_store_requires_fields(): void
    {
        $response = $this->postJson('/api/v1/sertifikat-akreditasi', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nama', 'deskripsi', 'foto_sertifikat']);
    }

    public function test_show_returns_detail(): void
    {
        $sertifikat = SertifikatAkreditasi::create([
            'nama' => 'A',
            'deskripsi' => 'D',
            'foto_sertifikat' => 'sertifikat_akreditasi/a.webp',
        ]);

        $response = $this->getJson('/api/v1/sertifikat-akreditasi/' . $sertifikat->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Detail sertifikat akreditasi',
            ])
            ->assertJsonPath('data.id', $sertifikat->id);
    }

    public function test_update_modifies_record(): void
    {
        $sertifikat = SertifikatAkreditasi::create([
            'nama' => 'A',
            'deskripsi' => 'D',
            'foto_sertifikat' => 'sertifikat_akreditasi/a.webp',
        ]);

        $file = UploadedFile::fake()->image('sertifikat-update.jpg');

        $payload = [
            'nama' => 'B',
            'foto_sertifikat' => $file,
        ];

        $response = $this->put('/api/v1/sertifikat-akreditasi/' . $sertifikat->id, $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Sertifikat akreditasi berhasil diperbarui',
            ]);

        $this->assertDatabaseHas('sertifikat_akreditasi', [
            'id' => $sertifikat->id,
            'nama' => 'B',
            'foto_sertifikat' => 'sertifikat_akreditasi/sertifikat.webp',
        ]);
    }

    public function test_destroy_deletes_record(): void
    {
        $sertifikat = SertifikatAkreditasi::create([
            'nama' => 'A',
            'deskripsi' => 'D',
            'foto_sertifikat' => 'sertifikat_akreditasi/a.webp',
        ]);

        $response = $this->deleteJson('/api/v1/sertifikat-akreditasi/' . $sertifikat->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Sertifikat akreditasi berhasil dihapus',
            ]);

        $this->assertDatabaseMissing('sertifikat_akreditasi', [
            'id' => $sertifikat->id,
        ]);
    }
}
