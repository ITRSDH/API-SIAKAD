<?php

namespace Tests\Feature;

use App\Models\Website\PmbPendaftaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PmbPendaftaranControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
    }

    public function test_index_returns_null_data_when_no_record_exists(): void
    {
        $response = $this->getJson('/api/v1/pmb-pendaftaran');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data profile kampus',
                'data' => null,
            ]);
    }

    public function test_show_returns_404_when_no_record_exists(): void
    {
        $response = $this->getJson('/api/v1/pmb-pendaftaran/any');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Profile kampus belum dibuat',
            ]);
    }

    public function test_store_creates_record_when_none_exists(): void
    {
        $payload = [
            'deskripsi' => 'Deskripsi PMB',
            'tata_cara' => 'Tata cara pendaftaran',
        ];

        $response = $this->postJson('/api/v1/pmb-pendaftaran', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'PMB Pendaftaran berhasil ditambahkan',
            ]);

        $this->assertDatabaseHas('pmb_pendaftaran', [
            'deskripsi' => $payload['deskripsi'],
            'tata_cara' => $payload['tata_cara'],
        ]);
    }

    public function test_store_updates_existing_record_when_one_exists(): void
    {
        $existing = PmbPendaftaran::create([
            'deskripsi' => 'Lama',
            'tata_cara' => 'Lama',
        ]);

        $payload = [
            'deskripsi' => 'Baru',
            'tata_cara' => 'Baru',
        ];

        $response = $this->postJson('/api/v1/pmb-pendaftaran', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'PMB Pendaftaran berhasil diperbarui',
            ]);

        $this->assertDatabaseHas('pmb_pendaftaran', [
            'id' => $existing->id,
            'deskripsi' => $payload['deskripsi'],
            'tata_cara' => $payload['tata_cara'],
        ]);
    }

    public function test_store_requires_deskripsi_and_tata_cara(): void
    {
        $response = $this->postJson('/api/v1/pmb-pendaftaran', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['deskripsi', 'tata_cara']);
    }

    public function test_show_returns_record_detail_when_record_exists(): void
    {
        $record = PmbPendaftaran::create([
            'deskripsi' => 'Deskripsi',
            'tata_cara' => 'Tata cara',
        ]);

        $response = $this->getJson('/api/v1/pmb-pendaftaran/' . $record->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Detail profile kampus',
            ])
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonPath('data.deskripsi', 'Deskripsi')
            ->assertJsonPath('data.tata_cara', 'Tata cara');
    }
}
