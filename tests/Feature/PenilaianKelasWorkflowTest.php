<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Siakad\Penilaian\KomponenPenilaianController;
use App\Http\Controllers\Api\Siakad\Penilaian\NilaiKomponenController;
use App\Models\Akademik\PenilaianKelas;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PenilaianKelasWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->rebuildSchema();
    }

    public function test_penilaian_kelas_workflow_locks_after_publish_and_allows_reopen(): void
    {
        $context = $this->createContext();

        $storeResponse = $this->callStoreKomponen($context['kelas_kuliah_id'], [
            'nama' => 'UAS',
            'bobot' => 100,
            'urutan' => 1,
            'is_active' => true,
        ]);

        $this->assertSame(201, $storeResponse->getStatusCode());
        $komponenId = $storeResponse->getData(true)['data']['id'];

        $this->assertDatabaseHas('penilaian_kelas', [
            'id_kelas_kuliah' => $context['kelas_kuliah_id'],
            'status' => PenilaianKelas::STATUS_DRAFT,
        ]);

        $syncResponse = $this->callSyncNilai($komponenId, [
            'nilai' => [
                [
                    'id_krs_detail' => $context['krs_detail_id'],
                    'nilai' => 80,
                    'catatan' => 'Nilai awal',
                ],
            ],
        ]);

        $this->assertSame(200, $syncResponse->getStatusCode());

        $publishResponse = $this->callPublishFinal($context['kelas_kuliah_id']);
        $this->assertSame(200, $publishResponse->getStatusCode());
        $publishPayload = $publishResponse->getData(true);
        $this->assertTrue($publishPayload['success']);

        $this->assertDatabaseHas('penilaian_kelas', [
            'id_kelas_kuliah' => $context['kelas_kuliah_id'],
            'status' => PenilaianKelas::STATUS_PUBLISHED,
        ]);

        $this->assertDatabaseHas('krs_detail', [
            'id' => $context['krs_detail_id'],
            'nilai_akhir' => 80,
            'nilai_huruf' => 'A-',
            'bobot_nilai' => 3.75,
            'status' => 'lulus',
        ]);

        $lockedSyncResponse = $this->callSyncNilai($komponenId, [
            'nilai' => [
                [
                    'id_krs_detail' => $context['krs_detail_id'],
                    'nilai' => 90,
                    'catatan' => 'Percobaan edit setelah publish',
                ],
            ],
        ]);

        $this->assertSame(422, $lockedSyncResponse->getStatusCode());
        $this->assertSame(
            PenilaianKelas::STATUS_PUBLISHED,
            $lockedSyncResponse->getData(true)['data']['status_penilaian']
        );

        $reopenResponse = $this->callReopen($context['kelas_kuliah_id'], [
            'reopen_reason' => 'Koreksi penilaian dosen',
        ]);

        $this->assertSame(200, $reopenResponse->getStatusCode());
        $this->assertDatabaseHas('penilaian_kelas', [
            'id_kelas_kuliah' => $context['kelas_kuliah_id'],
            'status' => PenilaianKelas::STATUS_REOPENED,
            'reopen_reason' => 'Koreksi penilaian dosen',
        ]);

        $resyncResponse = $this->callSyncNilai($komponenId, [
            'nilai' => [
                [
                    'id_krs_detail' => $context['krs_detail_id'],
                    'nilai' => 90,
                    'catatan' => 'Nilai revisi',
                ],
            ],
        ]);

        $this->assertSame(200, $resyncResponse->getStatusCode());
        $this->assertDatabaseHas('nilai_komponen', [
            'id_komponen_penilaian' => $komponenId,
            'id_krs_detail' => $context['krs_detail_id'],
            'nilai' => 90,
            'catatan' => 'Nilai revisi',
        ]);
    }

    private function createContext(): array
    {
        $kelasKuliahId = (string) Str::uuid();
        $krsDetailId = (string) Str::uuid();
        $pertemuanId = (string) Str::uuid();

        DB::table('kelas_kuliah')->insert([
            'id' => $kelasKuliahId,
            'id_prodi' => (string) Str::uuid(),
            'id_kurikulum_mata_kuliah' => (string) Str::uuid(),
            'id_semester' => (string) Str::uuid(),
            'nama_kelas' => 'IF-A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('krs_detail')->insert([
            'id' => $krsDetailId,
            'id_krs' => (string) Str::uuid(),
            'id_kelas_kuliah' => $kelasKuliahId,
            'status' => 'terdaftar',
            'nilai_akhir' => null,
            'nilai_huruf' => null,
            'bobot_nilai' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('pertemuan_kuliah')->insert([
            'id' => $pertemuanId,
            'id_kelas_kuliah' => $kelasKuliahId,
            'pertemuan_ke' => 1,
            'tanggal_pertemuan' => '2026-05-01',
            'materi' => 'Pengantar',
            'status' => 'selesai',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('presensi_kuliah')->insert([
            'id' => (string) Str::uuid(),
            'id_pertemuan_kuliah' => $pertemuanId,
            'id_krs_detail' => $krsDetailId,
            'status_kehadiran' => 'hadir',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'kelas_kuliah_id' => $kelasKuliahId,
            'krs_detail_id' => $krsDetailId,
        ];
    }

    private function rebuildSchema(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('khs_detail');
        Schema::dropIfExists('khs');
        Schema::dropIfExists('presensi_kuliah');
        Schema::dropIfExists('pertemuan_kuliah');
        Schema::dropIfExists('nilai_komponen');
        Schema::dropIfExists('komponen_penilaian');
        Schema::dropIfExists('penilaian_kelas');
        Schema::dropIfExists('academic_policies');
        Schema::dropIfExists('krs_detail');
        Schema::dropIfExists('kelas_kuliah');
        Schema::enableForeignKeyConstraints();

        Schema::create('kelas_kuliah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_prodi')->nullable();
            $table->uuid('id_kurikulum_mata_kuliah')->nullable();
            $table->uuid('id_semester')->nullable();
            $table->string('nama_kelas');
            $table->timestamps();
        });

        Schema::create('krs_detail', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_krs')->nullable();
            $table->uuid('id_kelas_kuliah');
            $table->string('status')->default('terdaftar');
            $table->text('catatan')->nullable();
            $table->decimal('nilai_akhir', 5, 2)->nullable();
            $table->string('nilai_huruf', 2)->nullable();
            $table->decimal('bobot_nilai', 3, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('academic_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('penilaian_kelas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_kelas_kuliah')->unique();
            $table->string('status')->default('draft');
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->uuid('published_by')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->uuid('reopened_by')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('komponen_penilaian', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_kelas_kuliah');
            $table->string('nama');
            $table->decimal('bobot', 5, 2);
            $table->unsignedInteger('urutan')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('nilai_komponen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_komponen_penilaian');
            $table->uuid('id_krs_detail');
            $table->decimal('nilai', 5, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('pertemuan_kuliah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_kelas_kuliah');
            $table->unsignedTinyInteger('pertemuan_ke');
            $table->date('tanggal_pertemuan')->nullable();
            $table->string('materi')->nullable();
            $table->text('catatan')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('presensi_kuliah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_pertemuan_kuliah');
            $table->uuid('id_krs_detail');
            $table->string('status_kehadiran')->default('hadir');
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    private function callStoreKomponen(string $kelasKuliahId, array $payload): \Illuminate\Http\JsonResponse
    {
        $controller = app(KomponenPenilaianController::class);
        $request = Request::create("/api/v1/penilaian/kelas/{$kelasKuliahId}/komponen", 'POST', $payload);

        return $controller->store($request, $kelasKuliahId);
    }

    private function callSyncNilai(string $komponenId, array $payload): \Illuminate\Http\JsonResponse
    {
        $controller = app(NilaiKomponenController::class);
        $request = Request::create("/api/v1/penilaian/komponen/{$komponenId}/nilai", 'PUT', $payload);

        return $controller->sync($request, $komponenId);
    }

    private function callPublishFinal(string $kelasKuliahId): \Illuminate\Http\JsonResponse
    {
        $controller = app(NilaiKomponenController::class);

        return $controller->publishFinal($kelasKuliahId);
    }

    private function callReopen(string $kelasKuliahId, array $payload): \Illuminate\Http\JsonResponse
    {
        $controller = app(NilaiKomponenController::class);
        $request = Request::create("/api/v1/penilaian/kelas/{$kelasKuliahId}/reopen", 'POST', $payload);

        return $controller->reopen($request, $kelasKuliahId);
    }
}
