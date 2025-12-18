<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Website\LandingContent;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class LandingContentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $authenticatedUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a user for testing
        $this->authenticatedUser = User::factory()->create();
        
        // Bypass all middleware for testing
        $this->withoutMiddleware();
    }

    public function test_index_returns_single_landing_content()
    {
        $content = LandingContent::factory()->create();

        $response = $this->getJson('/api/v1/landing-content');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id', 'hero_title', 'hero_subtitle', 'hero_background',
                    'jumlah_program_studi', 'jumlah_mahasiswa', 'jumlah_dosen', 'jumlah_mitra',
                    'keunggulan', 'logo', 'nama_aplikasi',
                    'deskripsi_footer', 'facebook', 'twitter', 'instagram', 'linkedin', 'youtube',
                    'alamat', 'telepon', 'email', 'created_at', 'updated_at'
                ]
            ])
            ->assertJsonFragment([
                'id' => $content->id,
                'hero_title' => $content->hero_title
            ]);
    }

    public function test_store_creates_new_landing_content_with_files()
    {
        $heroBackground = UploadedFile::fake()->image('hero.jpg');
        $logo = UploadedFile::fake()->image('logo.png');
        $data = [
            'hero_title' => 'Selamat Datang',
            'hero_subtitle' => 'Subjudul',
            'hero_background' => $heroBackground,
            'jumlah_program_studi' => 10,
            'jumlah_mahasiswa' => 1000,
            'jumlah_dosen' => 50,
            'jumlah_mitra' => 5,
            'keunggulan' => 'Unggul',
            'logo' => $logo,
            'nama_aplikasi' => 'SIAKAD',
            'deskripsi_footer' => 'Footer',
            'facebook' => 'fb',
            'twitter' => 'tw',
            'instagram' => 'ig',
            'linkedin' => 'li',
            'youtube' => 'yt',
            'alamat' => 'Alamat',
            'telepon' => '08123456789',
            'email' => 'test@email.com',
        ];

        $response = $this->post('/api/v1/landing-content', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Landing content berhasil ditambahkan',
            ]);

        $this->assertDatabaseHas('landing_content', [
            'hero_title' => $data['hero_title'],
            'nama_aplikasi' => $data['nama_aplikasi'],
            'email' => $data['email'],
        ]);

        $content = LandingContent::where('hero_title', $data['hero_title'])->first();
        $this->assertNotNull($content->logo);
        $this->assertNotNull($content->hero_background);
    }

    public function test_store_updates_existing_landing_content()
    {
        // Create existing content
        $existing = LandingContent::factory()->create([
            'hero_title' => 'Old Title',
            'nama_aplikasi' => 'Old App'
        ]);

        $heroBackground = UploadedFile::fake()->image('new_hero.jpg');
        $logo = UploadedFile::fake()->image('new_logo.png');
        $data = [
            'hero_title' => 'New Title',
            'hero_subtitle' => 'New Subtitle',
            'hero_background' => $heroBackground,
            'jumlah_program_studi' => 15,
            'jumlah_mahasiswa' => 2000,
            'jumlah_dosen' => 75,
            'jumlah_mitra' => 10,
            'keunggulan' => 'Sangat Unggul',
            'logo' => $logo,
            'nama_aplikasi' => 'New SIAKAD',
            'deskripsi_footer' => 'New Footer',
            'alamat' => 'New Address',
            'telepon' => '08987654321',
            'email' => 'new@email.com',
        ];

        $response = $this->post('/api/v1/landing-content', $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Landing content berhasil diperbarui',
            ]);

        // Should update the existing record, not create new one
        $this->assertEquals(1, LandingContent::count());
        
        $this->assertDatabaseHas('landing_content', [
            'id' => $existing->id,
            'hero_title' => $data['hero_title'],
            'nama_aplikasi' => $data['nama_aplikasi'],
            'email' => $data['email'],
        ]);

        $updated = LandingContent::first();
        $this->assertNotNull($updated->logo);
        $this->assertNotNull($updated->hero_background);
    }

    public function test_show_returns_landing_content_detail()
    {
        $content = LandingContent::factory()->create();

        // ID is ignored, always returns first content
        $response = $this->getJson('/api/v1/landing-content/999');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'success' => true,
                'message' => 'Detail landing content',
                'id' => $content->id,
                'hero_title' => $content->hero_title,
                'nama_aplikasi' => $content->nama_aplikasi,
                'email' => $content->email,
            ]);
    }

    public function test_show_returns_404_when_no_content_exists()
    {
        $response = $this->getJson('/api/v1/landing-content/1');

        $response->assertStatus(404)
            ->assertJsonFragment([
                'success' => false,
                'message' => 'Landing content belum dibuat'
            ]);
    }

    public function test_update_modifies_landing_content_with_files()
    {
        $content = LandingContent::factory()->create();
        $logo = UploadedFile::fake()->image('logo2.png');
        $heroBackground = UploadedFile::fake()->image('hero2.jpg');
        $data = [
            'hero_title' => 'Update Judul',
            'logo' => $logo,
            'hero_background' => $heroBackground,
            'nama_aplikasi' => 'UpdateApp',
            'email' => 'update@email.com',
        ];

        // ID is ignored, always updates first content
        $response = $this->put('/api/v1/landing-content/999', $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Landing content berhasil diperbarui',
            ]);

        $this->assertDatabaseHas('landing_content', [
            'id' => $content->id,
            'hero_title' => $data['hero_title'],
            'nama_aplikasi' => $data['nama_aplikasi'],
            'email' => $data['email'],
        ]);

        $content->refresh();
        $this->assertNotNull($content->logo);
        $this->assertNotNull($content->hero_background);
    }

    public function test_update_returns_404_when_no_content_exists()
    {
        $data = [
            'hero_title' => 'Update Judul',
            'nama_aplikasi' => 'UpdateApp',
            'email' => 'update@email.com',
        ];

        $response = $this->put('/api/v1/landing-content/1', $data);

        $response->assertStatus(404)
            ->assertJsonFragment([
                'success' => false,
                'message' => 'Landing content belum dibuat. Gunakan endpoint store untuk membuat data baru.'
            ]);
    }

    public function test_destroy_deletes_landing_content()
    {
        $content = LandingContent::factory()->create();

        // ID is ignored, always deletes first content
        $response = $this->deleteJson('/api/v1/landing-content/999');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Landing content berhasil dihapus',
            ]);

        $this->assertDatabaseMissing('landing_content', [
            'id' => $content->id
        ]);
    }

    public function test_destroy_returns_404_when_no_content_exists()
    {
        $response = $this->deleteJson('/api/v1/landing-content/1');

        $response->assertStatus(404)
            ->assertJsonFragment([
                'success' => false,
                'message' => 'Landing content tidak ditemukan'
            ]);
    }

    public function test_index_returns_null_when_no_content_exists()
    {
        $response = $this->getJson('/api/v1/landing-content');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'success' => true,
                'message' => 'Data landing content',
                'data' => null
            ]);
    }

    public function test_store_handles_null_integer_values()
    {
        $data = [
            'hero_title' => 'Test Title',
            'hero_subtitle' => 'Test Subtitle', 
            'jumlah_program_studi' => null,
            'jumlah_mahasiswa' => null,
            'jumlah_dosen' => null,
            'jumlah_mitra' => null,
            'nama_aplikasi' => 'Test App',
            'email' => 'test@example.com',
        ];

        $response = $this->post('/api/v1/landing-content', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Landing content berhasil ditambahkan',
            ]);

        $this->assertDatabaseHas('landing_content', [
            'hero_title' => 'Test Title',
            'jumlah_program_studi' => 0,
            'jumlah_mahasiswa' => 0,
            'jumlah_dosen' => 0,
            'jumlah_mitra' => 0,
        ]);
    }
}
