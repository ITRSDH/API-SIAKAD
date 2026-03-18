<?php

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
            Route::post('/change-password', [AuthController::class, 'changePassword']);
        });
    });
    // , 'check.role.permission'
    Route::middleware(['jwt.token', 'check.role.permission'])->group(function () {

        // Route::get('dashboard', [\App\Http\Controllers\Api\Siakad\ADMINISTRATOR\DashboardController::class, 'index'])->name('dashboard');

        Route::name('siakad.')->group(function () {
            Route::name('master.')->group(function () {

                Route::name('refrensi.')->group(function () {
                    Route::apiResource('prodi', \App\Http\Controllers\Api\Siakad\MasterData\ProdiController::class);
                    Route::put('prodi/{id}/kaprodi', [\App\Http\Controllers\Api\Siakad\MasterData\ProdiController::class, 'updateKaprodi'])->name('prodi.update-kaprodi');

                    Route::apiResource('tahun-akademik', \App\Http\Controllers\Api\Siakad\MasterData\TahunAkademikController::class);
                    Route::put('tahun-akademik/tahun-aktif/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\TahunAkademikController::class, 'setTahunAktif'])->name('tahun-akademik.tahun-aktif');
                    Route::put('tahun-akademik/semester-aktif/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\TahunAkademikController::class, 'setSemesterAktif'])->name('tahun-akademik.semester-aktif');

                    Route::get('kurikulum', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'index'])->name('kurikulum.index');
                    Route::get('kurikulum/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'show'])->name('kurikulum.show');
                    Route::post('kurikulum', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'store'])->name('kurikulum.store');
                    Route::put('kurikulum/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'update'])->name('kurikulum.update');
                    Route::delete('kurikulum/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'destroy'])->name('kurikulum.destroy');

                    Route::get('mata-kuliah/prodi/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'index'])->name('mata-kuliah.index');
                    Route::post('mata-kuliah/prodi/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'store'])->name('mata-kuliah.store');
                    Route::get('mata-kuliah/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'show'])->name('mata-kuliah.show');
                    Route::put('mata-kuliah/{id}/prodi/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'update'])->name('mata-kuliah.update');
                    Route::delete('mata-kuliah/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'destroy'])->name('mata-kuliah.destroy');

                    Route::apiResource('dosen', \App\Http\Controllers\Api\Siakad\MasterData\DosenController::class);

                    Route::apiResource('mahasiswa', \App\Http\Controllers\Api\Siakad\MasterData\MahasiswaController::class);

                    Route::get('profile-lulusan/prodi/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\ProfileLulusanController::class, 'index'])->name('profile-lulusan.index');
                    Route::post('profile-lulusan/prodi/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\ProfileLulusanController::class, 'store'])->name('profile-lulusan.store');
                    Route::get('profile-lulusan/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\ProfileLulusanController::class, 'show'])->name('profile-lulusan.show');
                    Route::put('profile-lulusan/{id}/prodi/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\ProfileLulusanController::class, 'update'])->name('profile-lulusan.update');
                    Route::delete('profile-lulusan/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\ProfileLulusanController::class, 'destroy'])->name('profile-lulusan.destroy');

                    Route::get('cpl/prodi/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\CPLController::class, 'index'])->name('cpl.index');
                    Route::post('cpl/prodi/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\CPLController::class, 'store'])->name('cpl.store');
                    Route::get('cpl/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\CPLController::class, 'show'])->name('cpl.show');
                    Route::put('cpl/{id}/prodi/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\CPLController::class, 'update'])->name('cpl.update');
                    Route::delete('cpl/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\CPLController::class, 'destroy'])->name('cpl.destroy');

                    // Indikator Kinerja
                    Route::get('indikator-kinerja/{id_cpl}', [\App\Http\Controllers\Api\Siakad\MasterData\IndikatorKinerjaController::class, 'index'])->name('indikator-kinerja.index');
                    Route::post('indikator-kinerja/{id_cpl}', [\App\Http\Controllers\Api\Siakad\MasterData\IndikatorKinerjaController::class, 'store'])->name('indikator-kinerja.store');
                    Route::get('indikator-kinerja/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\IndikatorKinerjaController::class, 'show'])->name('indikator-kinerja.show');
                    Route::put('indikator-kinerja/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\IndikatorKinerjaController::class, 'update'])->name('indikator-kinerja.update');
                    Route::delete('indikator-kinerja/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\IndikatorKinerjaController::class, 'destroy'])->name('indikator-kinerja.destroy');

                    // Pemetaan PL & CPL
                    Route::get('pemetaan-plcpl/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\PemetaanPLCPLController::class, 'index'])->name('pemetaan-pl-cpl.index');
                    Route::post('pemetaan-plcpl', [\App\Http\Controllers\Api\Siakad\MasterData\PemetaanPLCPLController::class, 'store'])->name('pemetaan-pl-cpl.store');

                    // Pemetaan CPL & MK
                    Route::get('pemetaan-cplmk/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\PemetaanCPLMKController::class, 'index'])->name('pemetaan-cpl-mk.index');
                    Route::post('pemetaan-cplmk', [\App\Http\Controllers\Api\Siakad\MasterData\PemetaanCPLMKController::class, 'store'])->name('pemetaan-cpl-mk.store');

                    // Mahasiswa Baru
                    Route::get('mahasiswa-baru', [\App\Http\Controllers\Api\Siakad\MasterData\MahasiswaBaruController::class, 'index'])->name('mahasiswa-baru.index');
                    Route::get('mahasiswa-baru/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\MahasiswaBaruController::class, 'show'])->name('mahasiswa-baru.show');
                    Route::post('mahasiswa-baru/sync', [\App\Http\Controllers\Api\Siakad\MasterData\MahasiswaBaruController::class, 'sync'])->name('mahasiswa-baru.sync');
                    Route::put('mahasiswa-baru/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\MahasiswaBaruController::class, 'update'])->name('mahasiswa-baru.update');
                    Route::delete('mahasiswa-baru/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\MahasiswaBaruController::class, 'destroy'])->name('mahasiswa-baru.destroy');
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

    Route::middleware('jwt.token')->group(function () {
        Route::get('dropdown', [\App\Http\Controllers\Api\DataDropdown\DropdownController::class, 'index'])->name('dropdown');

        // Import/Export Mata Kuliah
        Route::post('mata-kuliah/import/prodi/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'import'])->name('mata-kuliah.import');
        Route::get('mata-kuliah/export/prodi/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'export'])->name('mata-kuliah.export');
        Route::get('mata-kuliah/format/prodi/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'downloadFormat'])->name('mata-kuliah.format');

        // Import/Export Mahasiswa
        Route::get('mahasiswa/export', [\App\Http\Controllers\Api\Siakad\MasterData\MahasiswaController::class, 'export'])->name('mahasiswa.export');
        Route::post('mahasiswa/import/prodi/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\MahasiswaController::class, 'import'])->name('mahasiswa.import');
        Route::get('mahasiswa/template/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\MahasiswaController::class, 'exportTemplate'])->name('mahasiswa.template');

        Route::prefix('kurikulum')->group(function () {
            Route::get('/{id_kurikulum}/mata-kuliah-list', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'matakuliahByProdi'])->name('kurikulum.mata-kuliah-by-prodi');
            Route::get('/{id_kurikulum}/kurikulum-list', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'kurikulumByProdi'])->name('kurikulum.list-by-prodi');
            Route::post('/{id}/tambah-mata-kuliah', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'tambahMataKuliahManual']);
            Route::post('/{id_tujuan}/clone-mata-kuliah/{id_asal}', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'cloneMataKuliah']);
            Route::put('/{id}/mata-kuliah/{id_mk}', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'updateMataKuliah']);
            Route::delete('/{id}/mata-kuliah/{id_mk}', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'hapusMataKuliah']);
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
