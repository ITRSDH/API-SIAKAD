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

        // Route::get('dashboard', [\App\Http\Controllers\Api\Siakad\ADMINISTRATOR\DashboardController::class, 'index'])->name('dashboard');

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

                    // Mahasiswa Baru
                    Route::get('mahasiswa-baru', [\App\Http\Controllers\Api\Siakad\MasterData\MahasiswaBaruController::class, 'index'])->name('mahasiswa-baru.index');
                    Route::get('mahasiswa-baru/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\MahasiswaBaruController::class, 'show'])->name('mahasiswa-baru.show');
                    Route::post('mahasiswa-baru/sync', [\App\Http\Controllers\Api\Siakad\MasterData\MahasiswaBaruController::class, 'sync'])->name('mahasiswa-baru.sync');
                    Route::put('mahasiswa-baru/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\MahasiswaBaruController::class, 'update'])->name('mahasiswa-baru.update');
                    Route::delete('mahasiswa-baru/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\MahasiswaBaruController::class, 'destroy'])->name('mahasiswa-baru.destroy');

                    Route::apiResource('jenis-pembayaran', \App\Http\Controllers\Api\Siakad\MasterData\JenisPembayaranController::class);

                    Route::apiResource('ruang', \App\Http\Controllers\Api\Siakad\MasterData\RuangController::class);

                    Route::get('/dosen-mk/create', [\App\Http\Controllers\Api\Siakad\MasterData\DosenMKController::class, 'create'])->name('dosen-mk.create');
                    Route::get('/dosen-mk/{id}/edit', [\App\Http\Controllers\Api\Siakad\MasterData\DosenMKController::class, 'edit'])->name('dosen-mk.edit');
                    Route::apiResource('dosen-mk', \App\Http\Controllers\Api\Siakad\MasterData\DosenMKController::class);

                    Route::get('/jadwal-beban-ajar-dosen/{dosenmk}', [\App\Http\Controllers\Api\Siakad\MasterData\JadwalBebanAjarDosenController::class, 'createJadwalBebanajarDosen'])->name('jadwal-beban-ajar-dosen.create');
                    Route::get('/jadwal-beban-ajar-dosen/{dosenmk}/detail', [\App\Http\Controllers\Api\Siakad\MasterData\JadwalBebanAjarDosenController::class, 'getJadwalDetail'])->name('jadwal-beban-ajar-dosen.detail');
                    Route::post('/jadwal-beban-ajar-dosen', [\App\Http\Controllers\Api\Siakad\MasterData\JadwalBebanAjarDosenController::class, 'storeOrUpdateJadwalKuliah'])->name('jadwal-beban-ajar-dosen.store');
                    Route::put('/jadwal-beban-ajar-dosen/{dosenmk?}', [\App\Http\Controllers\Api\Siakad\MasterData\JadwalBebanAjarDosenController::class, 'storeOrUpdateJadwalKuliah'])->name('jadwal-beban-ajar-dosen.update');
                    Route::delete('/jadwal-beban-ajar-dosen/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\JadwalBebanAjarDosenController::class, 'deleteJadwal'])->name('jadwal-beban-ajar-dosen.delete');

                    // Mahasiswa Pengajuan KRS
                    Route::get('/pengajuan-krs/daftar-matkul', [\App\Http\Controllers\Api\Siakad\MAHASISWA\PengajuanKRSController::class, 'daftarMatkulPilihan'])->name('pengajuan-krs.daftar-matkul');
                    Route::post('/pengajuan-krs', [\App\Http\Controllers\Api\Siakad\MAHASISWA\PengajuanKRSController::class, 'pengajuanKrs'])->name('pengajuan-krs.pengajuan-krs');
                    Route::post('/draft', [\App\Http\Controllers\Api\Siakad\MAHASISWA\PengajuanKRSController::class, 'simpanDraftKrs'])->name('pengajuan-krs.simpan-draft');
                    Route::post('/{id}/submit', [\App\Http\Controllers\Api\Siakad\MAHASISWA\PengajuanKRSController::class, 'submitKrs'])->name('pengajuan-krs.submit');
                    Route::delete('/pengajuan-krs/{id}', [\App\Http\Controllers\Api\Siakad\MAHASISWA\PengajuanKRSController::class, 'batalPengajuanKrs'])->name('pengajuan-krs.batal-pengajuan');
                    Route::get('/pengajuan-krs/status', [\App\Http\Controllers\Api\Siakad\MAHASISWA\PengajuanKRSController::class, 'statusPengajuanKrs'])->name('pengajuan-krs.status');

                    // Dosen Wali Verifikasi KRS
                    Route::get('/dosen/verifikasi-krs/daftar-verifikasi', [\App\Http\Controllers\Api\Siakad\DOSEN\DosenWaliVerifikasiKRSController::class, 'daftarKrsPerluVerifikasi'])->name('dosen-verifikasi-krs.daftar-verifikasi');
                    Route::get('/dosen/verifikasi-krs/{id}', [\App\Http\Controllers\Api\Siakad\DOSEN\DosenWaliVerifikasiKRSController::class, 'detailKrs'])->name('dosen-verifikasi-krs.detail');
                    Route::post('/dosen/verifikasi-krs/{id}/approve', [\App\Http\Controllers\Api\Siakad\DOSEN\DosenWaliVerifikasiKRSController::class, 'approveKrs'])->name('dosen-verifikasi-krs.approve');
                    Route::post('/dosen/verifikasi-krs/{id}/reject', [\App\Http\Controllers\Api\Siakad\DOSEN\DosenWaliVerifikasiKRSController::class, 'rejectKrs'])->name('dosen-verifikasi-krs.reject');
                    Route::get('/dosen/verifikasi-krs/daftar-terverifikasi', [\App\Http\Controllers\Api\Siakad\DOSEN\DosenWaliVerifikasiKRSController::class, 'daftarKrsTerverifikasi'])->name('dosen-verifikasi-krs.daftar-terverifikasi');

                    // Dosen Mata kuliah - Get Nilai by Mahasiswa
                    Route::get('/dosenmk/mahasiswa', [\App\Http\Controllers\Api\Siakad\DOSEN\DosenMkgetmahasiswaController::class, 'getmahasiswa'])->name('dosen-matakuliah.get-mahasiswa');
                    Route::post('/dosenmk/nilai', [\App\Http\Controllers\Api\Siakad\DOSEN\DosenMkgetmahasiswaController::class, 'storeNilai'])->name('dosen-matakuliah.store-nilai');

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
