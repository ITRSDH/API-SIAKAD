<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Website\ProfileKampus;
use Illuminate\Http\UploadedFile;

class ProfileKampusControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_profile_kampus_list()
    {
        ProfileKampus::factory()->count(2)->create();
        $response = $this->getJson('/api/v1/profile-kampus');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success', 'message', 'data' => [[
                    'id', 'judul', 'deskripsi', 'visi', 'misi', 'struktur_image', 'fasilitas', 'created_at'
                ]]
            ]);
    }

    public function test_store_creates_profile_kampus()
    {
        $file = UploadedFile::fake()->image('struktur.jpg');
        $data = [
            'judul' => 'Profil Kampus',
            'deskripsi' => 'Deskripsi kampus',
            'visi' => 'Menjadi kampus unggul',
            'misi' => 'Mencetak lulusan berdaya saing',
            'struktur_image' => $file,
            'fasilitas' => 'Fasilitas lengkap',
        ];
        $response = $this->post('/api/v1/profile-kampus', $data);
        $response->assertStatus(201)
            ->assertJson(['success' => true]);
        $this->assertDatabaseHas('profile_kampus', ['judul' => 'Profil Kampus', 'visi' => 'Menjadi kampus unggul']);
        $profile = ProfileKampus::where('judul', $data['judul'])->first();
        $this->assertNotNull($profile->struktur_image);
    }

    public function test_show_returns_profile_kampus_detail()
    {
        $profile = ProfileKampus::factory()->create();
        $response = $this->getJson('/api/v1/profile-kampus/' . $profile->id);
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_update_modifies_profile_kampus()
    {
        $profile = ProfileKampus::factory()->create();
        $file = UploadedFile::fake()->image('struktur-update.jpg');
        $data = [
            'visi' => 'Menjadi kampus internasional',
            'struktur_image' => $file,
        ];
        $response = $this->put('/api/v1/profile-kampus/' . $profile->id, $data);
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        $this->assertDatabaseHas('profile_kampus', ['id' => $profile->id, 'visi' => 'Menjadi kampus internasional']);
        $profile->refresh();
        $this->assertNotNull($profile->struktur_image);
    }

    public function test_destroy_deletes_profile_kampus()
    {
        $profile = ProfileKampus::factory()->create();
        $response = $this->deleteJson('/api/v1/profile-kampus/' . $profile->id);
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
        $this->assertDatabaseMissing('profile_kampus', ['id' => $profile->id]);
    }
}
