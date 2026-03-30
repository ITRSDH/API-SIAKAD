<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Mockery;
use App\Services\ImageService;
use App\Models\Website\ProfileDosen;
use App\Http\Controllers\Api\Website\ProfileDosenController;

class ProfileDosenControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Define temporary routes to exercise the controller with FormRequests
        Route::post('/test/profile-dosen', [ProfileDosenController::class, 'store']);
        Route::get('/test/profile-dosen', [ProfileDosenController::class, 'index']);
        Route::get('/test/profile-dosen/{id}', [ProfileDosenController::class, 'show']);
        Route::put('/test/profile-dosen/{id}', [ProfileDosenController::class, 'update']);
        Route::delete('/test/profile-dosen/{id}', [ProfileDosenController::class, 'destroy']);
    }

    public function test_store_profile_dosen()
    {
        Storage::fake('public');

        // create a jenjang and prodi record so foreign key passes
        $jenjangId = (string) Str::uuid();
        DB::table('jenjang_pendidikan')->insert([
            'id' => $jenjangId,
            'kode_jenjang' => 'S1',
            'nama_jenjang' => 'Sarjana',
            'deskripsi' => null,
            'jumlah_semester' => 8,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $prodiId = (string) Str::uuid();
        DB::table('prodi')->insert([
            'id' => $prodiId,
            'id_jenjang_pendidikan' => $jenjangId,
            'kode_prodi' => 'S1-TEST',
            'nama_prodi' => 'Teknik Informatika',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mock ImageService
        $mock = Mockery::mock(ImageService::class);
        $mock->shouldReceive('convertToWebpAndReplace')->andReturn('profile_dosen/foto.webp');
        $this->app->instance(ImageService::class, $mock);

        $file = UploadedFile::fake()->image('foto.jpg');

        $response = $this->post('/test/profile-dosen', [
            'nama' => 'Budi',
            'nidn' => '12345',
            'status' => 'Dosen Tetap',
            'id_prodi' => $prodiId,
            'biografi' => 'Seorang dosen.',
            'foto' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['success','message','data' => ['id','nama','nidn','foto','status','id_prodi']]);

        $this->assertDatabaseHas('profile_dosen', [
            'nidn' => '12345',
            'nama' => 'Budi'
        ]);
    }

    public function test_index_and_show_return_profiles()
    {
        $id = (string) Str::uuid();
        $prodiId = (string) Str::uuid();
        $jenjangId = (string) Str::uuid();
        DB::table('jenjang_pendidikan')->insert(['id'=>$jenjangId,'kode_jenjang'=>'S1','nama_jenjang'=>'Sarjana','deskripsi'=>null,'jumlah_semester'=>8,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('prodi')->insert(['id'=>$prodiId,'id_jenjang_pendidikan'=>$jenjangId,'kode_prodi'=>'S1-T','nama_prodi'=>'Prodi','created_at'=>now(),'updated_at'=>now()]);

        ProfileDosen::create([
            'id' => $id,
            'nama' => 'Siti',
            'nidn' => '99999',
            'status' => 'Dosen',
            'id_prodi' => $prodiId,
            'biografi' => 'Bio',
        ]);

        $resp = $this->getJson('/test/profile-dosen');
        $resp->assertStatus(200)->assertJson(['success' => true]);

        $resp2 = $this->getJson('/test/profile-dosen/'.$id);
        $resp2->assertStatus(200)->assertJsonPath('data.id', $id);
    }

    public function test_update_profile_dosen()
    {
        Storage::fake('public');

        $id = (string) Str::uuid();
        $prodiId = (string) Str::uuid();
        $jenjangId = (string) Str::uuid();
        DB::table('jenjang_pendidikan')->insert(['id'=>$jenjangId,'kode_jenjang'=>'S1','nama_jenjang'=>'Sarjana','deskripsi'=>null,'jumlah_semester'=>8,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('prodi')->insert(['id'=>$prodiId,'id_jenjang_pendidikan'=>$jenjangId,'kode_prodi'=>'S1-T','nama_prodi'=>'Prodi','created_at'=>now(),'updated_at'=>now()]);

        ProfileDosen::create([
            'id' => $id,
            'nama' => 'Lama',
            'nidn' => '77777',
            'status' => 'Dosen',
            'id_prodi' => $prodiId,
            'biografi' => 'Bio',
        ]);

        $mock = Mockery::mock(ImageService::class);
        $mock->shouldReceive('convertToWebpAndReplace')->andReturn('profile_dosen/newfoto.webp');
        $this->app->instance(ImageService::class, $mock);

        $file = UploadedFile::fake()->image('new.jpg');

        $resp = $this->put('/test/profile-dosen/'.$id, [
            'nama' => 'Baru',
            'nidn' => '77777',
            'foto' => $file,
        ]);

        $resp->assertStatus(200)->assertJsonPath('data.nama', 'Baru');
        $this->assertDatabaseHas('profile_dosen', ['id' => $id, 'nama' => 'Baru']);
    }

    public function test_destroy_profile_dosen()
    {
        Storage::fake('public');

        $id = (string) Str::uuid();
        $prodiId = (string) Str::uuid();
        DB::table('prodi')->insert(['id'=>$prodiId,'nama_prodi'=>'Prodi','created_at'=>now(),'updated_at'=>now()]);

        // create a dummy file and profile
        Storage::disk('public')->put('profile_dosen/file.webp', '');

        ProfileDosen::create([
            'id' => $id,
            'nama' => 'ToDelete',
            'nidn' => '55555',
            'status' => 'Dosen',
            'id_prodi' => $prodiId,
            'biografi' => 'Bio',
            'foto' => 'profile_dosen/file.webp'
        ]);

        $mock = Mockery::mock(ImageService::class);
        $mock->shouldReceive('deletePublicFileIfExists')->with('profile_dosen/file.webp')->andReturnTrue();
        $this->app->instance(ImageService::class, $mock);

        $resp = $this->delete('/test/profile-dosen/'.$id);
        $resp->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseMissing('profile_dosen', ['id' => $id]);
    }
}
