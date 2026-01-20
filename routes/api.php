<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Website\FaqController;
use App\Http\Controllers\Api\Website\BeritaController;
use App\Http\Controllers\Api\Website\GaleriController;
use App\Http\Controllers\Api\Website\OrmawaController;
use App\Http\Controllers\Api\Website\BeasiswaController;
use App\Http\Controllers\Api\Website\PrestasiController;
use App\Http\Controllers\Api\Website\PengumumanController;
use App\Http\Controllers\Api\Website\ProfileKampusController;
use App\Http\Controllers\Api\Website\LandingContentController;
use App\Http\Controllers\Api\ManagementPengguna\RoleController;
use App\Http\Controllers\Api\ManagementPengguna\UserController;
use App\Http\Controllers\Api\ManagementPengguna\PermissionController;
use App\Http\Controllers\Api\Website\GetApiController;


// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::group(['middleware' => 'api'], function ($router) {
    Route::get('/', function () {
        return response()->json([
            'message' => 'Selamat Datang.',
        ]);
    });
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

    Route::middleware('jwt.token', 'check.role.permission')->group(function () {

        Route::get('dashboard/admin', [\App\Http\Controllers\Api\Siakad\ADMINISTRATOR\DashboardController::class, 'index'])->name('dashboard.admin');
        Route::get('dashboard/baak', [\App\Http\Controllers\Api\Siakad\BAAK\DashboardController::class, 'index'])->name('dashboard.baak');
        Route::get('dashboard/kaprodi', [\App\Http\Controllers\Api\Siakad\KAPRODI\DashboardController::class, 'index'])->name('dashboard.kaprodi');
        Route::get('dashboard/dosen-pa', [\App\Http\Controllers\Api\Siakad\DOSEN_PA\DashboardController::class, 'index'])->name('dashboard.dosen-pa');
        Route::get('dashboard/dosen-pengampu', [\App\Http\Controllers\Api\Siakad\DOSEN_PENGAMPU\DashboardController::class, 'index'])->name('dashboard.dosen-pengampu');
        Route::get('dashboard/mahasiswa', [\App\Http\Controllers\Api\Siakad\MAHASISWA\DashboardController::class, 'index'])->name('dashboard.mahasiswa');


        Route::name('siakad.')->group(function () {
            Route::name('master.')->group(function () {

                Route::name('refrensi.')->group(function () {
                    Route::apiResource('jenjang-pendidikan', \App\Http\Controllers\Api\Siakad\MasterData\JenjangPendidikanController::class);

                    Route::apiResource('prodi', \App\Http\Controllers\Api\Siakad\MasterData\ProdiController::class);
                    Route::put('prodi/{id}/kaprodi', [\App\Http\Controllers\Api\Siakad\MasterData\ProdiController::class, 'updateKaprodi'])->name('prodi.update-kaprodi');

                    Route::apiResource('tahun-akademik', \App\Http\Controllers\Api\Siakad\MasterData\TahunAkademikController::class);
                    Route::put('tahun-akademik/tahun-aktif/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\TahunAkademikController::class, 'setTahunAktif'])->name('tahun-akademik.tahun-aktif');
                    Route::put('tahun-akademik/semester-aktif/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\TahunAkademikController::class, 'setSemesterAktif'])->name('tahun-akademik.semester-aktif');

                    Route::apiResource('jenis-kelas', \App\Http\Controllers\Api\Siakad\MasterData\JenisKelasController::class);

                    Route::get('/kelas-mk', [\App\Http\Controllers\Api\Siakad\MasterData\KelasMKController::class, 'index'])->name('kelas-mk.index');
                    Route::get('/kelas-mk/create', [\App\Http\Controllers\Api\Siakad\MasterData\KelasMKController::class, 'create'])->name('kelas-mk.create');
                    Route::post('/kelas-mk', [\App\Http\Controllers\Api\Siakad\MasterData\KelasMKController::class, 'store'])->name('kelas-mk.store');
                    // Route::get('/kelas-mk/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\KelasMKController::class, 'show'])->name('kelas-mk.show');
                    Route::get('/kelas-mk/{id}/edit', [\App\Http\Controllers\Api\Siakad\MasterData\KelasMKController::class, 'edit'])->name('kelas-mk.edit');
                    Route::put('/kelas-mk/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\KelasMKController::class, 'update'])->name('kelas-mk.update');
                    Route::delete('/kelas-mk/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\KelasMKController::class, 'destroy'])->name('kelas-mk.destroy');


                    Route::apiResource('kelas-pararel', \App\Http\Controllers\Api\Siakad\MasterData\KelasPararelController::class);

                    Route::apiResource('kurikulum', \App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class);

                    Route::get('/mata-kuliah', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'index'])->name('mata-kuliah.index');
                    Route::get('/mata-kuliah/create', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'create'])->name('mata-kuliah.create');
                    Route::post('/mata-kuliah', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'store'])->name('mata-kuliah.store');
                    Route::get('/mata-kuliah/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'show'])->name('mata-kuliah.show');
                    Route::get('/mata-kuliah/semester/{semester}', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'edit'])->name('mata-kuliah.edit');
                    Route::put('/mata-kuliah/semester/{semester}', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'update'])->name('mata-kuliah.update');
                    Route::delete('/mata-kuliah/semester/{semester}', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'destroy'])->name('mata-kuliah.destroy');
                    Route::delete('/mata-kuliah/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'destroysigle'])->name('mata-kuliah.destroysigle');

                    Route::apiResource('dosen', \App\Http\Controllers\Api\Siakad\MasterData\DosenController::class);

                    Route::apiResource('mahasiswa', \App\Http\Controllers\Api\Siakad\MasterData\MahasiswaController::class);

                    Route::apiResource('jenis-pembayaran', \App\Http\Controllers\Api\Siakad\MasterData\JenisPembayaranController::class);

                    Route::apiResource('ruang', \App\Http\Controllers\Api\Siakad\MasterData\RuangController::class);

                    Route::apiResource('dosen-mk-jadwal', \App\Http\Controllers\Api\Siakad\MasterData\DosenMKJadwalController::class);
                    Route::get('/dosen-mk-jadwal/create', [\App\Http\Controllers\Api\Siakad\MasterData\DosenMKJadwalController::class, 'create'])->name('dosen-mk-jadwal.create');
                });
                // Route::name('setting-akademik.')->group(function () {});
            });
        });

        Route::name('pengguna.')->group(function () {
            Route::name('setting.')->group(function () {
                Route::apiResource('users', UserController::class);
                Route::apiResource('roles', RoleController::class);
                Route::apiResource('permissions', PermissionController::class);
                Route::post('/permissions/sync', [PermissionController::class, 'sync'])->name('permissions.sync');
            });
        });

        // End Master Data

        // Landing Website Kampus
        Route::name('websitekampus.')->group(function () {
            Route::name('landing.')->group(function () {
                Route::apiResource('pengumuman', PengumumanController::class);
                Route::apiResource('prestasi', PrestasiController::class);
                Route::apiResource('beasiswa', BeasiswaController::class);
                Route::apiResource('berita', BeritaController::class);
                Route::apiResource('galeri', GaleriController::class);
                Route::apiResource('faq', FaqController::class);
                Route::apiResource('landing-content', LandingContentController::class);
                Route::apiResource('ormawa', OrmawaController::class);
                Route::apiResource('profile-kampus', ProfileKampusController::class);
            });
        });
    });


    // Public API Routes for Website Kampus
    Route::get('/landing/pengumuman', [GetApiController::class, 'pengumuman'])->name('landing.pengumuman');
    Route::get('/landing/pengumuman/{id}', [GetApiController::class, 'pengumumanDetail'])->name('landing.pengumuman.detail');
    Route::get('/landing/prestasi', [GetApiController::class, 'prestasi'])->name('landing.prestasi');
    Route::get('/landing/prestasi/{id}', [GetApiController::class, 'prestasiDetail'])->name('landing.prestasi.detail');
    Route::get('/landing/landing-content', [GetApiController::class, 'landingContent'])->name('landing.content');
    Route::get('/landing/beasiswa', [GetApiController::class, 'beasiswa'])->name('landing.beasiswa');
    Route::get('/landing/beasiswa/{id}', [GetApiController::class, 'beasiswaDetail'])->name('landing.beasiswa.detail');
    Route::get('/landing/berita', [GetApiController::class, 'berita'])->name('landing.berita');
    Route::get('/landing/berita/{id}', [GetApiController::class, 'beritaDetail'])->name('landing.berita.detail');
    Route::get('/landing/galeri', [GetApiController::class, 'galeri'])->name('landing.galeri');
    Route::get('/landing/galeri/{id}', [GetApiController::class, 'galeriDetail'])->name('landing.galeri.detail');
    Route::get('/landing/faq', [GetApiController::class, 'faq'])->name('landing.faq');
    Route::get('/landing/ormawa', [GetApiController::class, 'ormawa'])->name('landing.ormawa');
    Route::get('/landing/ormawa/{id}', [GetApiController::class, 'ormawaDetail'])->name('landing.ormawa.detail');
    Route::get('/landing/profile-kampus', [GetApiController::class, 'profileKampus'])->name('landing.profile-kampus');
    Route::get('/landing/prodi', [GetApiController::class, 'prodi'])->name('landing.prodi');
    Route::get('/landing/prodi/{id}', [GetApiController::class, 'prodiDetail'])->name('landing.prodi.detail');
    Route::get('/landing/prodi/{id}/prestasi', [GetApiController::class, 'prodiPrestasi'])->name('landing.prodi.prestasi');
});
