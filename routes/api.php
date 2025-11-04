<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Website\FaqController;
use App\Http\Controllers\Api\MasterData\KhsController;
use App\Http\Controllers\Api\MasterData\KrsController;
use App\Http\Controllers\Api\Website\BeritaController;
use App\Http\Controllers\Api\Website\GaleriController;
use App\Http\Controllers\Api\Website\OrmawaController;
use App\Http\Controllers\Api\MasterData\DosenController;
use App\Http\Controllers\Api\MasterData\NilaiController;
use App\Http\Controllers\Api\MasterData\ProdiController;
use App\Http\Controllers\Api\MasterData\RuangController;
use App\Http\Controllers\Api\Website\BeasiswaController;
use App\Http\Controllers\Api\Website\PrestasiController;
use App\Http\Controllers\Api\MasterData\AlumniController;
use App\Http\Controllers\Api\MasterData\KelasMkController;
use App\Http\Controllers\Api\Website\PengumumanController;
use App\Http\Controllers\Api\MasterData\PresensiController;
use App\Http\Controllers\Api\MasterData\SemesterController;
use App\Http\Controllers\Api\MasterData\KhsDetailController;
use App\Http\Controllers\Api\MasterData\KrsDetailController;
use App\Http\Controllers\Api\MasterData\KurikulumController;
use App\Http\Controllers\Api\MasterData\MahasiswaController;
use App\Http\Controllers\Api\MasterData\PerwalianController;
use App\Http\Controllers\Api\MasterData\JenisKelasController;
use App\Http\Controllers\Api\MasterData\MataKuliahController;
use App\Http\Controllers\Api\Website\ProfileKampusController;
use App\Http\Controllers\Api\Website\LandingContentController;
use App\Http\Controllers\Api\ManagementPengguna\RoleController;
use App\Http\Controllers\Api\ManagementPengguna\UserController;
use App\Http\Controllers\Api\MasterData\DosenKelasMkController;
use App\Http\Controllers\Api\MasterData\JadwalKuliahController;
use App\Http\Controllers\Api\MasterData\KelasPararelController;
use App\Http\Controllers\Api\MasterData\TahunAkademikController;
use App\Http\Controllers\Api\MasterData\BebanAjarDosenController;
use App\Http\Controllers\Api\MasterData\KelasMahasiswaController;
use App\Http\Controllers\Api\MasterData\BerkasMahasiswaController;
use App\Http\Controllers\Api\MasterData\JenisPembayaranController;
use App\Http\Controllers\Api\MasterData\JenjangPendidikanController;
use App\Http\Controllers\Api\ManagementPengguna\PermissionController;
use App\Http\Controllers\Api\MasterData\PembayaranMahasiswaController;
use App\Http\Controllers\Api\MasterData\StatusAkademikMahasiswaController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::group(['middleware' => 'api'], function ($router) {
    Route::get('/', function () {
        return response()->json([
            'message' => 'Selamat Datang.'
        ]);
    })->name('api.hello');
});

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('refresh', [AuthController::class, 'refresh']);

        Route::middleware('jwt.token')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    Route::middleware('jwt.token')->group(function () {

        Route::apiResource('users', UserController::class);
        Route::apiResource('roles', RoleController::class);
        Route::apiResource('permissions', PermissionController::class);

        // Start Master Data
        // Jenjang Pendidikan
        Route::apiResource('jenjang-pendidikan', JenjangPendidikanController::class);

        // Program Studi
        Route::apiResource('prodi', ProdiController::class);
        Route::get('/all-prodi', [ProdiController::class, 'getAllData']);

        // Tahun Akademik
        Route::apiResource('tahun-akademik', TahunAkademikController::class);
        Route::post('/tahun-akademik/{id}/aktif', [TahunAkademikController::class, 'setAktif']);

        Route::apiResource('semester', SemesterController::class);
        Route::get('/all-semester', [SemesterController::class, 'getAllData']);

        Route::apiResource('kurikulum', KurikulumController::class);
        Route::get('/all-kurikulum', [KurikulumController::class, 'getAllData']);

        Route::apiResource('mata-kuliah', MataKuliahController::class);
        Route::get('/all-mata-kuliah', [MataKuliahController::class, 'getAllData']);
        Route::get('/edit-mata-kuliah/{id}', [MataKuliahController::class, 'getEditData']);

        Route::apiResource('jenis-kelas', JenisKelasController::class);

        Route::apiResource('kelas-pararel', KelasPararelController::class);
        Route::get('/all-kelas-pararel', [KelasPararelController::class, 'getAllData']);

        Route::apiResource('ruang', RuangController::class);

        Route::apiResource('dosen', DosenController::class);

        Route::apiResource('mahasiswa', MahasiswaController::class);

        Route::apiResource('kelas-mk', KelasMkController::class);

        Route::apiResource('jadwal-kuliah', JadwalKuliahController::class);

        Route::apiResource('dosen-kelas-mk', DosenKelasMkController::class);

        Route::apiResource('beban-ajar-dosen', BebanAjarDosenController::class);

        Route::apiResource('presensi', PresensiController::class);

        Route::apiResource('nilai', NilaiController::class);

        Route::apiResource('krs', KrsController::class);

        Route::apiResource('krs-detail', KrsDetailController::class);

        Route::apiResource('khs', KhsController::class);

        Route::apiResource('khs-detail', KhsDetailController::class);

        Route::apiResource('jenis-pembayaran', JenisPembayaranController::class);

        Route::apiResource('pembayaran-mahasiswa', PembayaranMahasiswaController::class);

        Route::apiResource('status-akademik-mahasiswa', StatusAkademikMahasiswaController::class);

        Route::apiResource('perwalian', PerwalianController::class);

        Route::apiResource('berkas-mahasiswa', BerkasMahasiswaController::class);

        Route::apiResource('alumni', AlumniController::class);

        Route::apiResource('kelas-mahasiswa', KelasMahasiswaController::class);

        // End Master Data

        // Start Website Landing Page
        Route::apiResource('pengumuman', PengumumanController::class);
        Route::apiResource('prestasi', PrestasiController::class);
        Route::apiResource('beasiswa', BeasiswaController::class);
        Route::apiResource('berita', BeritaController::class);
        Route::apiResource('galeri', GaleriController::class);
        Route::apiResource('faq', FaqController::class);
        Route::apiResource('landing-content', LandingContentController::class);
        Route::apiResource('ormawa', OrmawaController::class);
        Route::apiResource('profile-kampus', ProfileKampusController::class);
        // End Website Landing Page
    });
});
