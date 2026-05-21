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
use App\Http\Controllers\Api\Website\PmbPendaftaranController;
use App\Http\Controllers\Api\Website\SertifikatAkreditasiController;
use App\Http\Controllers\Api\Website\LandingContentController;
use App\Http\Controllers\Api\ManagementPengguna\RoleController;
use App\Http\Controllers\Api\ManagementPengguna\UserController;
use App\Http\Controllers\Api\ManagementPengguna\PermissionController;
use App\Http\Controllers\Api\Website\GetApiController;
use App\Http\Controllers\Api\Website\ProfileDosenController;
use App\Http\Controllers\Api\Siakad\Administratif\WisudaController;
use App\Http\Controllers\Api\Siakad\Akademik\AcademicPolicyController;
use App\Http\Controllers\Api\Siakad\Krs\KRSMahasiswaController;
use App\Http\Controllers\Api\Siakad\Krs\KRSDosenWaliController;
use App\Http\Controllers\Api\Siakad\MasterData\KelaskuliahController;

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
                    Route::apiResource('periode-krs', \App\Http\Controllers\Api\Siakad\MasterData\PeriodeKrsController::class);
                    Route::apiResource('ruang-kuliah', \App\Http\Controllers\Api\Siakad\MasterData\RuangKuliahController::class);
                    Route::apiResource('semester', \App\Http\Controllers\Api\Siakad\MasterData\SemesterController::class);

                    Route::get('kurikulum', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'index'])->name('kurikulum.index');
                    Route::get('kurikulum/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'show'])->name('kurikulum.show');
                    Route::post('kurikulum', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'store'])->name('kurikulum.store');
                    Route::put('kurikulum/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'update'])->name('kurikulum.update');
                    Route::delete('kurikulum/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'destroy'])->name('kurikulum.destroy');
                    Route::get('konversi-mata-kuliah', [\App\Http\Controllers\Api\Siakad\MasterData\KonversiMataKuliahController::class, 'index'])->name('konversi-mata-kuliah.index');
                    Route::get('konversi-mata-kuliah/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\KonversiMataKuliahController::class, 'show'])->name('konversi-mata-kuliah.show');
                    Route::post('konversi-mata-kuliah', [\App\Http\Controllers\Api\Siakad\MasterData\KonversiMataKuliahController::class, 'store'])->name('konversi-mata-kuliah.store');
                    Route::put('konversi-mata-kuliah/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\KonversiMataKuliahController::class, 'update'])->name('konversi-mata-kuliah.update');
                    Route::delete('konversi-mata-kuliah/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\KonversiMataKuliahController::class, 'destroy'])->name('konversi-mata-kuliah.destroy');

                    Route::get('mata-kuliah/prodi/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'index'])->name('mata-kuliah.index');
                    Route::post('mata-kuliah/prodi/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'store'])->name('mata-kuliah.store');
                    Route::get('mata-kuliah/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'show'])->name('mata-kuliah.show');
                    Route::get('mata-kuliah/{id}/prasyarat', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'getPrasyarat'])->name('mata-kuliah.prasyarat');
                    Route::put('mata-kuliah/{id}/prasyarat', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'syncPrasyarat'])->name('mata-kuliah.sync-prasyarat');
                    Route::put('mata-kuliah/{id}/prodi/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'update'])->name('mata-kuliah.update');
                    Route::delete('mata-kuliah/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'destroy'])->name('mata-kuliah.destroy');

                    Route::get('kelas-kuliah', [\App\Http\Controllers\Api\Siakad\MasterData\KelaskuliahController::class, 'index'])->name('kelas-kuliah.index');
                    Route::get('kelas-kuliah/dosen-saya', [\App\Http\Controllers\Api\Siakad\MasterData\KelasKuliahController::class, 'kelasDosenSaya'])->name('kelas-kuliah.dosen-saya');
                    Route::get('kelas-kuliah/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\KelaskuliahController::class, 'show'])->name('kelas-kuliah.show');
                    Route::post('kelas-kuliah', [\App\Http\Controllers\Api\Siakad\MasterData\KelaskuliahController::class, 'store'])->name('kelas-kuliah.store');
                    Route::put('kelas-kuliah/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\KelaskuliahController::class, 'update'])->name('kelas-kuliah.update');
                    Route::delete('kelas-kuliah/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\KelaskuliahController::class, 'destroy'])->name('kelas-kuliah.destroy');

                    Route::apiResource('dosen', \App\Http\Controllers\Api\Siakad\MasterData\DosenController::class);

                    Route::apiResource('mahasiswa', \App\Http\Controllers\Api\Siakad\MasterData\MahasiswaController::class);
                    Route::get('mahasiswa/{id}/riwayat-kurikulum', [\App\Http\Controllers\Api\Siakad\MasterData\MahasiswaController::class, 'riwayatKurikulum'])->name('mahasiswa.riwayat-kurikulum');
                    Route::post('mahasiswa/{id}/migrasi-kurikulum', [\App\Http\Controllers\Api\Siakad\MasterData\MahasiswaController::class, 'migrateKurikulum'])->name('mahasiswa.migrasi-kurikulum');

                    Route::get('dosen-wali', [\App\Http\Controllers\Api\Siakad\MasterData\DosenWaliController::class, 'index'])->name('dosen-wali.index');
                    Route::get('dosen-wali/mahasiswa', [\App\Http\Controllers\Api\Siakad\MasterData\DosenWaliController::class, 'getMahasiswa'])->name('dosen-wali.mahasiswa');
                    Route::get('dosen-wali/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\DosenWaliController::class, 'detail'])->name('dosen-wali.detail');
                    Route::post('dosen-wali/assign', [\App\Http\Controllers\Api\Siakad\MasterData\DosenWaliController::class, 'assign'])->name('dosen-wali.assign');
                    Route::post('dosen-wali/unassign', [\App\Http\Controllers\Api\Siakad\MasterData\DosenWaliController::class, 'unassign'])->name('dosen-wali.unassign');
                    Route::post('dosen-wali/remove', [\App\Http\Controllers\Api\Siakad\MasterData\DosenWaliController::class, 'remove'])->name('dosen-wali.remove');

                    Route::get('dosen-pengajar-kelas/kelas/{id_kelas_kuliah}', [\App\Http\Controllers\Api\Siakad\MasterData\DosenPengajarKelasController::class, 'index'])->name('dosen-pengajar-kelas.index');
                    Route::get('dosen-pengajar-kelas/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\DosenPengajarKelasController::class, 'show'])->name('dosen-pengajar-kelas.show');
                    Route::post('dosen-pengajar-kelas/kelas/{id_kelas_kuliah}', [\App\Http\Controllers\Api\Siakad\MasterData\DosenPengajarKelasController::class, 'store'])->name('dosen-pengajar-kelas.store');
                    Route::put('dosen-pengajar-kelas/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\DosenPengajarKelasController::class, 'update'])->name('dosen-pengajar-kelas.update');
                    Route::delete('dosen-pengajar-kelas/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\DosenPengajarKelasController::class, 'destroy'])->name('dosen-pengajar-kelas.destroy');

                    Route::get('jadwal-kuliah/kelas/{id_kelas_kuliah}', [\App\Http\Controllers\Api\Siakad\MasterData\JadwalKuliahController::class, 'index'])->name('jadwal-kuliah.index');
                    Route::get('jadwal-kuliah/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\JadwalKuliahController::class, 'show'])->name('jadwal-kuliah.show');
                    Route::post('jadwal-kuliah/kelas/{id_kelas_kuliah}', [\App\Http\Controllers\Api\Siakad\MasterData\JadwalKuliahController::class, 'store'])->name('jadwal-kuliah.store');
                    Route::put('jadwal-kuliah/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\JadwalKuliahController::class, 'update'])->name('jadwal-kuliah.update');
                    Route::delete('jadwal-kuliah/{id}', [\App\Http\Controllers\Api\Siakad\MasterData\JadwalKuliahController::class, 'destroy'])->name('jadwal-kuliah.destroy');

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

        // Akademik - KRS
        // Route::name('akademik.')->group(function () {
        //     Route::name('krs.')->group(function () {
        //         Route::get('krs', [KRSController::class, 'index'])->name('index');
        //         Route::get('krs/{id}', [KRSController::class, 'show'])->name('show');
        //         Route::post('krs', [KRSController::class, 'store'])->name('store');
        //         Route::get('krs/available-mata-kuliah/{mahasiswaId}/{semesterId}', [KRSController::class, 'getAvailableMataKuliah'])->name('available-mata-kuliah');
        //         Route::post('krs/add-mata-kuliah', [KRSController::class, 'addMataKuliah'])->name('add-mata-kuliah');
        //         Route::delete('krs/{krsId}/remove-mata-kuliah/{kelasKuliahId}', [KRSController::class, 'removeMataKuliah'])->name('remove-mata-kuliah');
        //         Route::post('krs/approve', [KRSController::class, 'approve'])->name('approve');
        //         Route::post('krs/reject', [KRSController::class, 'reject'])->name('reject');
        //         Route::get('krs/statistics', [KRSController::class, 'statistics'])->name('statistics');
        //     });
        // });

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
                Route::apiResource('profile-dosen', ProfileDosenController::class);
                Route::apiResource('pmb-pendaftaran', PmbPendaftaranController::class)->only(['index', 'store', 'show']);
                Route::apiResource('sertifikat-akreditasi', SertifikatAkreditasiController::class);
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
            Route::post(
                '{id}/tambah-mata-kuliah-checkbox',
                [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'tambahMataKuliahCheckbox']
            );
            Route::post('/{id_tujuan}/clone-mata-kuliah/{id_asal}', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'cloneMataKuliah']);
            Route::put('/{id}/mata-kuliah/{id_mk}', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'updateMataKuliah']);
            Route::delete('/{id}/mata-kuliah/{id_mk}', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'hapusMataKuliah']);
        });

        Route::name('akademik.')->group(function () {
            Route::name('remedial.')->group(function () {
                Route::get('remedial', [\App\Http\Controllers\Api\Siakad\Akademik\RemedialController::class, 'index'])->name('index');
                Route::get('remedial/{id}', [\App\Http\Controllers\Api\Siakad\Akademik\RemedialController::class, 'show'])->name('show');
                Route::post('remedial', [\App\Http\Controllers\Api\Siakad\Akademik\RemedialController::class, 'store'])->name('store');
                Route::post('remedial/{id}/publish', [\App\Http\Controllers\Api\Siakad\Akademik\RemedialController::class, 'publish'])->name('publish');
                Route::post('remedial/{id}/cancel', [\App\Http\Controllers\Api\Siakad\Akademik\RemedialController::class, 'cancel'])->name('cancel');
            });

            Route::name('pertemuan.')->group(function () {
                Route::get('pertemuan-kuliah/kelas/{id_kelas_kuliah}', [\App\Http\Controllers\Api\Siakad\Akademik\PertemuanKuliahController::class, 'index'])->name('index');
                Route::post('pertemuan-kuliah/kelas/{id_kelas_kuliah}', [\App\Http\Controllers\Api\Siakad\Akademik\PertemuanKuliahController::class, 'store'])->name('store');
                Route::put('pertemuan-kuliah/{id}', [\App\Http\Controllers\Api\Siakad\Akademik\PertemuanKuliahController::class, 'update'])->name('update');
            });

            Route::name('presensi.')->group(function () {
                Route::get('presensi-kuliah/kelas/{id_kelas_kuliah}/rekap', [\App\Http\Controllers\Api\Siakad\Akademik\PresensiKuliahController::class, 'rekapKelas'])->name('rekap-kelas');
                Route::get('presensi-kuliah/pertemuan/{id_pertemuan_kuliah}', [\App\Http\Controllers\Api\Siakad\Akademik\PresensiKuliahController::class, 'index'])->name('index');
                Route::post('presensi-kuliah/pertemuan/{id_pertemuan_kuliah}/generate-peserta', [\App\Http\Controllers\Api\Siakad\Akademik\PresensiKuliahController::class, 'generatePeserta'])->name('generate-peserta');
                Route::put('presensi-kuliah/pertemuan/{id_pertemuan_kuliah}', [\App\Http\Controllers\Api\Siakad\Akademik\PresensiKuliahController::class, 'sync'])->name('sync');
            });

            Route::name('khs.')->group(function () {
                Route::get('khs', [\App\Http\Controllers\Api\Siakad\Akademik\KHSController::class, 'index'])->name('index');
                Route::get('khs/{id}', [\App\Http\Controllers\Api\Siakad\Akademik\KHSController::class, 'show'])->name('show');
                Route::post('khs/generate', [\App\Http\Controllers\Api\Siakad\Akademik\KHSController::class, 'generate'])->name('generate');
                Route::get('khs/preview/semester', [\App\Http\Controllers\Api\Siakad\Akademik\KHSController::class, 'preview'])->name('preview');
            });

            Route::name('transkrip.')->group(function () {
                Route::get('transkrip', [\App\Http\Controllers\Api\Siakad\Akademik\TranskripController::class, 'index'])->name('index');
                Route::get('transkrip/{id}', [\App\Http\Controllers\Api\Siakad\Akademik\TranskripController::class, 'show'])->name('show');
                Route::get('transkrip/preview/mahasiswa', [\App\Http\Controllers\Api\Siakad\Akademik\TranskripController::class, 'preview'])->name('preview');
                Route::post('transkrip/generate', [\App\Http\Controllers\Api\Siakad\Akademik\TranskripController::class, 'generate'])->name('generate');
            });

            Route::name('yudisium.')->group(function () {
                Route::get('yudisium', [\App\Http\Controllers\Api\Siakad\Akademik\YudisiumController::class, 'index'])->name('index');
                Route::get('yudisium/{id}', [\App\Http\Controllers\Api\Siakad\Akademik\YudisiumController::class, 'show'])->name('show');
                Route::get('yudisium/preview/mahasiswa', [\App\Http\Controllers\Api\Siakad\Akademik\YudisiumController::class, 'preview'])->name('preview');
                Route::post('yudisium/generate', [\App\Http\Controllers\Api\Siakad\Akademik\YudisiumController::class, 'generate'])->name('generate');
            });

            Route::name('kelulusan.')->group(function () {
                Route::get('kelulusan', [\App\Http\Controllers\Api\Siakad\Akademik\KelulusanController::class, 'index'])->name('index');
                Route::get('kelulusan/{id}', [\App\Http\Controllers\Api\Siakad\Akademik\KelulusanController::class, 'show'])->name('show');
                Route::post('kelulusan/generate', [\App\Http\Controllers\Api\Siakad\Akademik\KelulusanController::class, 'generate'])->name('generate');
            });

            Route::name('penilaian.')->group(function () {
                Route::get('penilaian/kelas/{id_kelas_kuliah}/komponen', [\App\Http\Controllers\Api\Siakad\Penilaian\KomponenPenilaianController::class, 'index'])->name('komponen.index');
                Route::post('penilaian/kelas/{id_kelas_kuliah}/komponen', [\App\Http\Controllers\Api\Siakad\Penilaian\KomponenPenilaianController::class, 'store'])->name('komponen.store');
                Route::put('penilaian/komponen/{id}', [\App\Http\Controllers\Api\Siakad\Penilaian\KomponenPenilaianController::class, 'update'])->name('komponen.update');
                Route::delete('penilaian/komponen/{id}', [\App\Http\Controllers\Api\Siakad\Penilaian\KomponenPenilaianController::class, 'destroy'])->name('komponen.destroy');

                Route::get('penilaian/kelas/{id_kelas_kuliah}/nilai', [\App\Http\Controllers\Api\Siakad\Penilaian\NilaiKomponenController::class, 'index'])->name('nilai.index');
                Route::put('penilaian/komponen/{id_komponen_penilaian}/nilai', [\App\Http\Controllers\Api\Siakad\Penilaian\NilaiKomponenController::class, 'sync'])->name('nilai.sync');
                Route::post('penilaian/kelas/{id_kelas_kuliah}/publish-final', [\App\Http\Controllers\Api\Siakad\Penilaian\NilaiKomponenController::class, 'publishFinal'])->name('nilai.publish-final');
                Route::post('penilaian/kelas/{id_kelas_kuliah}/reopen', [\App\Http\Controllers\Api\Siakad\Penilaian\NilaiKomponenController::class, 'reopen'])->name('nilai.reopen');
                Route::put('penilaian/krs-detail/{id_krs_detail}/manual-final', [\App\Http\Controllers\Api\Siakad\Penilaian\NilaiKomponenController::class, 'setManualFinal'])->name('nilai.manual-final');
            });

            Route::name('kebijakan.')->group(function () {
                Route::get('academic-policies', [AcademicPolicyController::class, 'index'])->name('index');
                Route::put('academic-policies', [AcademicPolicyController::class, 'update'])->name('update');
            });

            // KRS untuk Mahasiswa
            Route::name('krs-mahasiswa.')->group(function () {
                Route::get('krs-mahasiswa', [KRSMahasiswaController::class, 'index'])->name('index');
                Route::get('krs-mahasiswa/current', [KRSMahasiswaController::class, 'current'])->name('current');
                Route::get('krs-mahasiswa/statistics', [KRSMahasiswaController::class, 'statistics'])->name('statistics');
                Route::get('krs-mahasiswa/validation-summary', [KRSMahasiswaController::class, 'validationSummary'])->name('validation-summary');
                Route::get('krs-mahasiswa/available-mata-kuliah', [KRSMahasiswaController::class, 'getAvailableMataKuliah'])->name('available-mata-kuliah');
                Route::get('krs-mahasiswa/repeat-candidates', [KRSMahasiswaController::class, 'repeatCandidates'])->name('repeat-candidates');
                Route::post('krs-mahasiswa', [KRSMahasiswaController::class, 'store'])->name('store');
                Route::post('krs-mahasiswa/current/init', [KRSMahasiswaController::class, 'initCurrent'])->name('current.init');
                Route::post('krs-mahasiswa/add-mata-kuliah', [KRSMahasiswaController::class, 'addMataKuliah'])->name('add-mata-kuliah');
                Route::post('krs-mahasiswa/submit', [KRSMahasiswaController::class, 'submit'])->name('submit');
                Route::delete('krs-mahasiswa/{krsId}/remove-mata-kuliah/{kelasKuliahId}', [KRSMahasiswaController::class, 'removeMataKuliah'])->name('remove-mata-kuliah');
                Route::get('krs-mahasiswa/{id}', [KRSMahasiswaController::class, 'show'])->name('show');
            });

            // KRS untuk Dosen Wali
            Route::name('krs-dosen.')->group(function () {
                Route::get('krs-dosen', [KRSDosenWaliController::class, 'index'])->name('index');
                Route::get('krs-dosen/mahasiswa-bimbingan', [KRSDosenWaliController::class, 'getMahasiswaBimbingan'])->name('mahasiswa-bimbingan');
                Route::get('krs-dosen/mahasiswa/{mahasiswaId}', [KRSDosenWaliController::class, 'getKRSByMahasiswa'])->name('krs-by-mahasiswa');
                Route::get('krs-dosen/pending', [KRSDosenWaliController::class, 'getPendingKRS'])->name('pending');
                Route::get('krs-dosen/statistics', [KRSDosenWaliController::class, 'statistics'])->name('statistics');
                Route::post('krs-dosen/approve', [KRSDosenWaliController::class, 'approve'])->name('approve');
                Route::post('krs-dosen/revision', [KRSDosenWaliController::class, 'revision'])->name('revision');
                Route::post('krs-dosen/reject', [KRSDosenWaliController::class, 'reject'])->name('reject');
                Route::post('krs-dosen/bulk-approve', [KRSDosenWaliController::class, 'bulkApprove'])->name('bulk-approve');
                Route::get('krs-dosen/{id}', [KRSDosenWaliController::class, 'show'])->name('show');
            });

            Route::name('tugas-akhir.')->group(function () {
                Route::get('tugas-akhir', [\App\Http\Controllers\Api\Siakad\Akademik\TugasAkhirController::class, 'index'])->name('index');
                Route::get('tugas-akhir/{id}', [\App\Http\Controllers\Api\Siakad\Akademik\TugasAkhirController::class, 'show'])->name('show');
                Route::post('tugas-akhir', [\App\Http\Controllers\Api\Siakad\Akademik\TugasAkhirController::class, 'store'])->name('store');
                Route::put('tugas-akhir/{id}', [\App\Http\Controllers\Api\Siakad\Akademik\TugasAkhirController::class, 'update'])->name('update');
                Route::put('tugas-akhir/{id}/pembimbing', [\App\Http\Controllers\Api\Siakad\Akademik\TugasAkhirController::class, 'syncPembimbing'])->name('sync-pembimbing');
                Route::post('tugas-akhir/{id}/ujian', [\App\Http\Controllers\Api\Siakad\Akademik\TugasAkhirController::class, 'storeUjian'])->name('store-ujian');
                Route::put('tugas-akhir/ujian/{id}', [\App\Http\Controllers\Api\Siakad\Akademik\TugasAkhirController::class, 'updateUjian'])->name('update-ujian');
            });
        });
    });

    Route::middleware(['jwt.token', 'check.role.permission'])->group(function () {
        Route::name('administratif.')->group(function () {
            Route::name('wisuda.')->group(function () {
                Route::get('wisuda/periode', [WisudaController::class, 'indexPeriode'])->name('periode.index');
                Route::get('wisuda/periode/{id}', [WisudaController::class, 'showPeriode'])->name('periode.show');
                Route::post('wisuda/periode', [WisudaController::class, 'storePeriode'])->name('periode.store');
                Route::put('wisuda/periode/{id}', [WisudaController::class, 'updatePeriode'])->name('periode.update');

                Route::get('wisuda/periode/{id_periode_wisuda}/peserta', [WisudaController::class, 'indexPeserta'])->name('peserta.index');
                Route::post('wisuda/periode/{id_periode_wisuda}/peserta', [WisudaController::class, 'storePeserta'])->name('peserta.store');
                Route::get('wisuda/peserta/{id}', [WisudaController::class, 'showPeserta'])->name('peserta.show');
                Route::put('wisuda/peserta/{id}', [WisudaController::class, 'updatePeserta'])->name('peserta.update');
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
    Route::get('/landing/profile-dosen', [GetApiController::class, 'profileDosen'])->name('landing.profile-dosen');
    Route::get('/landing/profile-dosen/limit', [GetApiController::class, 'profileDosenLimit'])->name('landing.profile-dosen.limit');
    Route::get('/landing/profile-dosen/{id}', [GetApiController::class, 'profileDosenDetail'])->name('landing.profile-dosen.detail');
    Route::get('/landing/sertifikat-akreditasi', [GetApiController::class, 'sertifikatAkreditasi'])->name('landing.sertifikat-akreditasi');
    Route::get('/landing/sertifikat-akreditasi/{id}', [GetApiController::class, 'sertifikatAkreditasiDetail'])->name('landing.sertifikat-akreditasi.detail');
    Route::get('/landing/pmb-pendaftaran', [GetApiController::class, 'pmbPendaftaran'])->name('landing.pmb-pendaftaran');
    
    // Route TEST 
    Route::get('kelas-kuliah-test', [\App\Http\Controllers\Api\Siakad\MasterData\KelaskuliahController::class, 'index'])->name('kelas-kuliah.index.test');
});

// Route::middleware('jwt.token')->group(function () {
//     Route::get('dropdown', [\App\Http\Controllers\Api\DataDropdown\DropdownController::class, 'index'])->name('dropdown');

//     // Import/Export Mata Kuliah
//     Route::post('mata-kuliah/import/prodi/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'import'])->name('mata-kuliah.import');
//     Route::get('mata-kuliah/export/prodi/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'export'])->name('mata-kuliah.export');
//     Route::get('mata-kuliah/format/prodi/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\MataKuliahController::class, 'downloadFormat'])->name('mata-kuliah.format');

//     // Import/Export Mahasiswa
//     Route::get('mahasiswa/export', [\App\Http\Controllers\Api\Siakad\MasterData\MahasiswaController::class, 'export'])->name('mahasiswa.export');
//     Route::post('mahasiswa/import/prodi/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\MahasiswaController::class, 'import'])->name('mahasiswa.import');
//     Route::get('mahasiswa/template/{id_prodi}', [\App\Http\Controllers\Api\Siakad\MasterData\MahasiswaController::class, 'exportTemplate'])->name('mahasiswa.template');

//     Route::prefix('kurikulum')->group(function () {
//         Route::get('/{id_kurikulum}/mata-kuliah-list', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'matakuliahByProdi'])->name('kurikulum.mata-kuliah-by-prodi');
//         Route::get('/{id_kurikulum}/kurikulum-list', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'kurikulumByProdi'])->name('kurikulum.list-by-prodi');
//         Route::post('/{id}/tambah-mata-kuliah', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'tambahMataKuliahManual']);
//         Route::post(
//             '{id}/tambah-mata-kuliah-checkbox',
//             [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'tambahMataKuliahCheckbox']
//         );
//         Route::post('/{id_tujuan}/clone-mata-kuliah/{id_asal}', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'cloneMataKuliah']);
//         Route::put('/{id}/mata-kuliah/{id_mk}', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'updateMataKuliah']);
//         Route::delete('/{id}/mata-kuliah/{id_mk}', [\App\Http\Controllers\Api\Siakad\MasterData\KurikulumController::class, 'hapusMataKuliah']);
//     });

//     Route::name('akademik.')->group(function () {
//         Route::name('remedial.')->group(function () {
//             Route::get('remedial', [\App\Http\Controllers\Api\Siakad\Akademik\RemedialController::class, 'index'])->name('index');
//             Route::get('remedial/{id}', [\App\Http\Controllers\Api\Siakad\Akademik\RemedialController::class, 'show'])->name('show');
//             Route::post('remedial', [\App\Http\Controllers\Api\Siakad\Akademik\RemedialController::class, 'store'])->name('store');
//             Route::post('remedial/{id}/publish', [\App\Http\Controllers\Api\Siakad\Akademik\RemedialController::class, 'publish'])->name('publish');
//             Route::post('remedial/{id}/cancel', [\App\Http\Controllers\Api\Siakad\Akademik\RemedialController::class, 'cancel'])->name('cancel');
//         });

//         Route::name('pertemuan.')->group(function () {
//             Route::get('pertemuan-kuliah/kelas/{id_kelas_kuliah}', [\App\Http\Controllers\Api\Siakad\Akademik\PertemuanKuliahController::class, 'index'])->name('index');
//             Route::post('pertemuan-kuliah/kelas/{id_kelas_kuliah}', [\App\Http\Controllers\Api\Siakad\Akademik\PertemuanKuliahController::class, 'store'])->name('store');
//             Route::put('pertemuan-kuliah/{id}', [\App\Http\Controllers\Api\Siakad\Akademik\PertemuanKuliahController::class, 'update'])->name('update');
//         });

//         Route::name('presensi.')->group(function () {
//             Route::get('presensi-kuliah/kelas/{id_kelas_kuliah}/rekap', [\App\Http\Controllers\Api\Siakad\Akademik\PresensiKuliahController::class, 'rekapKelas'])->name('rekap-kelas');
//             Route::get('presensi-kuliah/pertemuan/{id_pertemuan_kuliah}', [\App\Http\Controllers\Api\Siakad\Akademik\PresensiKuliahController::class, 'index'])->name('index');
//             Route::post('presensi-kuliah/pertemuan/{id_pertemuan_kuliah}/generate-peserta', [\App\Http\Controllers\Api\Siakad\Akademik\PresensiKuliahController::class, 'generatePeserta'])->name('generate-peserta');
//             Route::put('presensi-kuliah/pertemuan/{id_pertemuan_kuliah}', [\App\Http\Controllers\Api\Siakad\Akademik\PresensiKuliahController::class, 'sync'])->name('sync');
//         });

//         Route::name('khs.')->group(function () {
//             Route::get('khs', [\App\Http\Controllers\Api\Siakad\Akademik\KHSController::class, 'index'])->name('index');
//             Route::get('khs/{id}', [\App\Http\Controllers\Api\Siakad\Akademik\KHSController::class, 'show'])->name('show');
//             Route::post('khs/generate', [\App\Http\Controllers\Api\Siakad\Akademik\KHSController::class, 'generate'])->name('generate');
//             Route::get('khs/preview/semester', [\App\Http\Controllers\Api\Siakad\Akademik\KHSController::class, 'preview'])->name('preview');
//         });

//         Route::name('transkrip.')->group(function () {
//             Route::get('transkrip', [\App\Http\Controllers\Api\Siakad\Akademik\TranskripController::class, 'index'])->name('index');
//             Route::get('transkrip/{id}', [\App\Http\Controllers\Api\Siakad\Akademik\TranskripController::class, 'show'])->name('show');
//             Route::get('transkrip/preview/mahasiswa', [\App\Http\Controllers\Api\Siakad\Akademik\TranskripController::class, 'preview'])->name('preview');
//             Route::post('transkrip/generate', [\App\Http\Controllers\Api\Siakad\Akademik\TranskripController::class, 'generate'])->name('generate');
//         });

//         Route::name('yudisium.')->group(function () {
//             Route::get('yudisium', [\App\Http\Controllers\Api\Siakad\Akademik\YudisiumController::class, 'index'])->name('index');
//             Route::get('yudisium/{id}', [\App\Http\Controllers\Api\Siakad\Akademik\YudisiumController::class, 'show'])->name('show');
//             Route::get('yudisium/preview/mahasiswa', [\App\Http\Controllers\Api\Siakad\Akademik\YudisiumController::class, 'preview'])->name('preview');
//             Route::post('yudisium/generate', [\App\Http\Controllers\Api\Siakad\Akademik\YudisiumController::class, 'generate'])->name('generate');
//         });

//         Route::name('kelulusan.')->group(function () {
//             Route::get('kelulusan', [\App\Http\Controllers\Api\Siakad\Akademik\KelulusanController::class, 'index'])->name('index');
//             Route::get('kelulusan/{id}', [\App\Http\Controllers\Api\Siakad\Akademik\KelulusanController::class, 'show'])->name('show');
//             Route::post('kelulusan/generate', [\App\Http\Controllers\Api\Siakad\Akademik\KelulusanController::class, 'generate'])->name('generate');
//         });

//         Route::name('penilaian.')->group(function () {
//             Route::get('penilaian/kelas/{id_kelas_kuliah}/komponen', [\App\Http\Controllers\Api\Siakad\Penilaian\KomponenPenilaianController::class, 'index'])->name('komponen.index');
//             Route::post('penilaian/kelas/{id_kelas_kuliah}/komponen', [\App\Http\Controllers\Api\Siakad\Penilaian\KomponenPenilaianController::class, 'store'])->name('komponen.store');
//             Route::put('penilaian/komponen/{id}', [\App\Http\Controllers\Api\Siakad\Penilaian\KomponenPenilaianController::class, 'update'])->name('komponen.update');
//             Route::delete('penilaian/komponen/{id}', [\App\Http\Controllers\Api\Siakad\Penilaian\KomponenPenilaianController::class, 'destroy'])->name('komponen.destroy');

//             Route::get('penilaian/kelas/{id_kelas_kuliah}/nilai', [\App\Http\Controllers\Api\Siakad\Penilaian\NilaiKomponenController::class, 'index'])->name('nilai.index');
//             Route::put('penilaian/komponen/{id_komponen_penilaian}/nilai', [\App\Http\Controllers\Api\Siakad\Penilaian\NilaiKomponenController::class, 'sync'])->name('nilai.sync');
//             Route::post('penilaian/kelas/{id_kelas_kuliah}/publish-final', [\App\Http\Controllers\Api\Siakad\Penilaian\NilaiKomponenController::class, 'publishFinal'])->name('nilai.publish-final');
//             Route::post('penilaian/kelas/{id_kelas_kuliah}/reopen', [\App\Http\Controllers\Api\Siakad\Penilaian\NilaiKomponenController::class, 'reopen'])->name('nilai.reopen');
//             Route::put('penilaian/krs-detail/{id_krs_detail}/manual-final', [\App\Http\Controllers\Api\Siakad\Penilaian\NilaiKomponenController::class, 'setManualFinal'])->name('nilai.manual-final');
//         });

//         Route::name('kebijakan.')->group(function () {
//             Route::get('academic-policies', [AcademicPolicyController::class, 'index'])->name('index');
//             Route::put('academic-policies', [AcademicPolicyController::class, 'update'])->name('update');
//         });

//         // KRS untuk Mahasiswa
//         Route::name('krs-mahasiswa.')->group(function () {
//             Route::get('krs-mahasiswa', [KRSMahasiswaController::class, 'index'])->name('index');
//             Route::get('krs-mahasiswa/current', [KRSMahasiswaController::class, 'current'])->name('current');
//             Route::get('krs-mahasiswa/statistics', [KRSMahasiswaController::class, 'statistics'])->name('statistics');
//             Route::get('krs-mahasiswa/validation-summary', [KRSMahasiswaController::class, 'validationSummary'])->name('validation-summary');
//             Route::get('krs-mahasiswa/available-mata-kuliah', [KRSMahasiswaController::class, 'getAvailableMataKuliah'])->name('available-mata-kuliah');
//             Route::get('krs-mahasiswa/repeat-candidates', [KRSMahasiswaController::class, 'repeatCandidates'])->name('repeat-candidates');
//             Route::post('krs-mahasiswa', [KRSMahasiswaController::class, 'store'])->name('store');
//             Route::post('krs-mahasiswa/current/init', [KRSMahasiswaController::class, 'initCurrent'])->name('current.init');
//             Route::post('krs-mahasiswa/add-mata-kuliah', [KRSMahasiswaController::class, 'addMataKuliah'])->name('add-mata-kuliah');
//             Route::post('krs-mahasiswa/submit', [KRSMahasiswaController::class, 'submit'])->name('submit');
//             Route::delete('krs-mahasiswa/{krsId}/remove-mata-kuliah/{kelasKuliahId}', [KRSMahasiswaController::class, 'removeMataKuliah'])->name('remove-mata-kuliah');
//             Route::get('krs-mahasiswa/{id}', [KRSMahasiswaController::class, 'show'])->name('show');
//         });

//         // KRS untuk Dosen Wali
//         Route::name('krs-dosen.')->group(function () {
//             Route::get('krs-dosen', [KRSDosenWaliController::class, 'index'])->name('index');
//             Route::get('krs-dosen/mahasiswa-bimbingan', [KRSDosenWaliController::class, 'getMahasiswaBimbingan'])->name('mahasiswa-bimbingan');
//             Route::get('krs-dosen/mahasiswa/{mahasiswaId}', [KRSDosenWaliController::class, 'getKRSByMahasiswa'])->name('krs-by-mahasiswa');
//             Route::get('krs-dosen/pending', [KRSDosenWaliController::class, 'getPendingKRS'])->name('pending');
//             Route::get('krs-dosen/statistics', [KRSDosenWaliController::class, 'statistics'])->name('statistics');
//             Route::post('krs-dosen/approve', [KRSDosenWaliController::class, 'approve'])->name('approve');
//             Route::post('krs-dosen/revision', [KRSDosenWaliController::class, 'revision'])->name('revision');
//             Route::post('krs-dosen/reject', [KRSDosenWaliController::class, 'reject'])->name('reject');
//             Route::post('krs-dosen/bulk-approve', [KRSDosenWaliController::class, 'bulkApprove'])->name('bulk-approve');
//             Route::get('krs-dosen/{id}', [KRSDosenWaliController::class, 'show'])->name('show');
//         });

//         Route::name('tugas-akhir.')->group(function () {
//             Route::get('tugas-akhir', [\App\Http\Controllers\Api\Siakad\Akademik\TugasAkhirController::class, 'index'])->name('index');
//             Route::get('tugas-akhir/{id}', [\App\Http\Controllers\Api\Siakad\Akademik\TugasAkhirController::class, 'show'])->name('show');
//             Route::post('tugas-akhir', [\App\Http\Controllers\Api\Siakad\Akademik\TugasAkhirController::class, 'store'])->name('store');
//             Route::put('tugas-akhir/{id}', [\App\Http\Controllers\Api\Siakad\Akademik\TugasAkhirController::class, 'update'])->name('update');
//             Route::put('tugas-akhir/{id}/pembimbing', [\App\Http\Controllers\Api\Siakad\Akademik\TugasAkhirController::class, 'syncPembimbing'])->name('sync-pembimbing');
//             Route::post('tugas-akhir/{id}/ujian', [\App\Http\Controllers\Api\Siakad\Akademik\TugasAkhirController::class, 'storeUjian'])->name('store-ujian');
//             Route::put('tugas-akhir/ujian/{id}', [\App\Http\Controllers\Api\Siakad\Akademik\TugasAkhirController::class, 'updateUjian'])->name('update-ujian');
//         });
//     });
// });

// Route::middleware(['jwt.token', 'check.role.permission'])->group(function () {
//     Route::name('administratif.')->group(function () {
//         Route::name('wisuda.')->group(function () {
//             Route::get('wisuda/periode', [WisudaController::class, 'indexPeriode'])->name('periode.index');
//             Route::get('wisuda/periode/{id}', [WisudaController::class, 'showPeriode'])->name('periode.show');
//             Route::post('wisuda/periode', [WisudaController::class, 'storePeriode'])->name('periode.store');
//             Route::put('wisuda/periode/{id}', [WisudaController::class, 'updatePeriode'])->name('periode.update');

//             Route::get('wisuda/periode/{id_periode_wisuda}/peserta', [WisudaController::class, 'indexPeserta'])->name('peserta.index');
//             Route::post('wisuda/periode/{id_periode_wisuda}/peserta', [WisudaController::class, 'storePeserta'])->name('peserta.store');
//             Route::get('wisuda/peserta/{id}', [WisudaController::class, 'showPeserta'])->name('peserta.show');
//             Route::put('wisuda/peserta/{id}', [WisudaController::class, 'updatePeserta'])->name('peserta.update');
//         });
//     });
// });

// // Public API Routes for Website Kampus
// Route::get('/landing/pengumuman', [GetApiController::class, 'pengumuman'])->name('landing.pengumuman');
// Route::get('/landing/pengumuman/{id}', [GetApiController::class, 'pengumumanDetail'])->name('landing.pengumuman.detail');
// Route::get('/landing/prestasi', [GetApiController::class, 'prestasi'])->name('landing.prestasi');
// Route::get('/landing/prestasi/{id}', [GetApiController::class, 'prestasiDetail'])->name('landing.prestasi.detail');
// Route::get('/landing/landing-content', [GetApiController::class, 'landingContent'])->name('landing.content');
// Route::get('/landing/beasiswa', [GetApiController::class, 'beasiswa'])->name('landing.beasiswa');
// Route::get('/landing/beasiswa/{id}', [GetApiController::class, 'beasiswaDetail'])->name('landing.beasiswa.detail');
// Route::get('/landing/berita', [GetApiController::class, 'berita'])->name('landing.berita');
// Route::get('/landing/berita/{id}', [GetApiController::class, 'beritaDetail'])->name('landing.berita.detail');
// Route::get('/landing/galeri', [GetApiController::class, 'galeri'])->name('landing.galeri');
// Route::get('/landing/galeri/{id}', [GetApiController::class, 'galeriDetail'])->name('landing.galeri.detail');
// Route::get('/landing/faq', [GetApiController::class, 'faq'])->name('landing.faq');
// Route::get('/landing/ormawa', [GetApiController::class, 'ormawa'])->name('landing.ormawa');
// Route::get('/landing/ormawa/{id}', [GetApiController::class, 'ormawaDetail'])->name('landing.ormawa.detail');
// Route::get('/landing/profile-kampus', [GetApiController::class, 'profileKampus'])->name('landing.profile-kampus');
// Route::get('/landing/prodi', [GetApiController::class, 'prodi'])->name('landing.prodi');
// Route::get('/landing/prodi/{id}', [GetApiController::class, 'prodiDetail'])->name('landing.prodi.detail');
// Route::get('/landing/prodi/{id}/prestasi', [GetApiController::class, 'prodiPrestasi'])->name('landing.prodi.prestasi');
