<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Website\LandingContent;
use Illuminate\Http\UploadedFile;

class LandingContentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_landing_content_list()
    {
        LandingContent::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/landing-content');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id', 'hero_title', 'hero_subtitle', 'hero_background',
                        'jumlah_program_studi', 'jumlah_mahasiswa', 'jumlah_dosen', 'jumlah_mitra',
                        'keunggulan', 'logo', 'nama_aplikasi',
                        'deskripsi_footer', 'facebook', 'twitter', 'instagram', 'linkedin', 'youtube',
                        'alamat', 'telepon', 'email', 'created_at', 'updated_at'
                    ]
                ]
            ]);
    }

    public function test_store_creates_landing_content_with_files()
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

    public function test_show_returns_landing_content_detail()
    {
        $content = LandingContent::factory()->create();

        $response = $this->getJson('/api/v1/landing-content/' . $content->id);

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

        $response = $this->put('/api/v1/landing-content/' . $content->id, $data);

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

    public function test_destroy_deletes_landing_content()
    {
        $content = LandingContent::factory()->create();

        $response = $this->deleteJson('/api/v1/landing-content/' . $content->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Landing content berhasil dihapus',
            ]);

        $this->assertDatabaseMissing('landing_content', [
            'id' => $content->id
        ]);
    }
}
