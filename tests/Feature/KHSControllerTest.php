<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Siakad\Akademik\KHSController;
use App\Models\Akademik\KHS;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class KHSControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $this->rebuildAcademicSchema();
    }

    public function test_khs_preview_rejects_when_there_are_pending_results(): void
    {
        $context = $this->createAcademicContext();

        $this->createKrsDetail($context, [
            'status' => 'lulus',
            'nilai_akhir' => 86,
            'nilai_huruf' => 'A',
            'bobot_nilai' => 4.00,
        ]);

        $pendingDetail = $this->createKrsDetail($context, [
            'status' => 'terdaftar',
            'nilai_akhir' => null,
            'nilai_huruf' => null,
            'bobot_nilai' => null,
        ], [
            'kode_mk' => 'MK002',
            'nama_mk' => 'Algoritma',
            'sks' => 2,
        ]);

        $response = $this->callKhsPreview([
            'id_mahasiswa' => $context['mahasiswa_id'],
            'id_semester' => $context['semester_id'],
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertFalse($payload['success']);
        $this->assertSame($pendingDetail['krs_detail_id'], $payload['data']['pending_krs_detail_ids'][0]);
    }

    public function test_khs_generate_excludes_drop_and_counts_failed_course_in_ips(): void
    {
        $context = $this->createAcademicContext();

        $this->createKrsDetail($context, [
            'status' => 'lulus',
            'nilai_akhir' => 86,
            'nilai_huruf' => 'A',
            'bobot_nilai' => 4.00,
        ], [
            'kode_mk' => 'MK001',
            'nama_mk' => 'Basis Data',
            'sks' => 3,
        ]);

        $this->createKrsDetail($context, [
            'status' => 'tidak_lulus',
            'nilai_akhir' => 40,
            'nilai_huruf' => 'D',
            'bobot_nilai' => 1.00,
        ], [
            'kode_mk' => 'MK002',
            'nama_mk' => 'Algoritma',
            'sks' => 2,
        ]);

        $this->createKrsDetail($context, [
            'status' => 'drop',
            'nilai_akhir' => null,
            'nilai_huruf' => null,
            'bobot_nilai' => null,
        ], [
            'kode_mk' => 'MK003',
            'nama_mk' => 'Jaringan',
            'sks' => 1,
        ]);

        $response = $this->callKhsGenerate([
            'id_mahasiswa' => $context['mahasiswa_id'],
            'id_semester' => $context['semester_id'],
            'is_final' => false,
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertTrue($payload['success']);
        $this->assertSame(5, $payload['data']['total_sks_diambil']);
        $this->assertSame(3, $payload['data']['total_sks_lulus']);
        $this->assertSame('2.80', $payload['data']['ips']);

        $khsId = $payload['data']['id'];

        $this->assertDatabaseHas('khs', [
            'id' => $khsId,
            'total_sks_diambil' => 5,
            'total_sks_lulus' => 3,
        ]);

        $this->assertSame(2, DB::table('khs_detail')->where('id_khs', $khsId)->count());
        $this->assertDatabaseMissing('khs_detail', [
            'id_khs' => $khsId,
            'status' => 'drop',
        ]);
    }

    public function test_khs_generate_rejects_when_existing_snapshot_is_final(): void
    {
        $context = $this->createAcademicContext();

        $this->createKrsDetail($context, [
            'status' => 'lulus',
            'nilai_akhir' => 90,
            'nilai_huruf' => 'A',
            'bobot_nilai' => 4.00,
        ]);

        KHS::create([
            'id' => (string) Str::uuid(),
            'id_mahasiswa' => $context['mahasiswa_id'],
            'id_semester' => $context['semester_id'],
            'total_sks_diambil' => 3,
            'total_sks_lulus' => 3,
            'ips' => 4.00,
            'ipk' => 4.00,
            'is_final' => true,
            'generated_at' => now(),
        ]);

        $response = $this->callKhsGenerate([
            'id_mahasiswa' => $context['mahasiswa_id'],
            'id_semester' => $context['semester_id'],
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertFalse($payload['success']);
        $this->assertSame('KHS yang sudah difinalisasi tidak dapat digenerate ulang', $payload['message']);
    }

    private function createAcademicContext(): array
    {
        $tahunAkademikId = (string) Str::uuid();
        $semesterId = (string) Str::uuid();
        $prodiId = (string) Str::uuid();
        $mahasiswaId = (string) Str::uuid();
        $krsId = (string) Str::uuid();

        DB::table('tahun_akademik')->insert([
            'id' => $tahunAkademikId,
            'tahun_akademik' => '2025/2026',
            'status_aktif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('semester')->insert([
            'id' => $semesterId,
            'id_tahun_akademik' => $tahunAkademikId,
            'nama_semester' => 'Ganjil',
            'kode_semester' => '20251',
            'tanggal_mulai' => '2025-08-01',
            'tanggal_selesai' => '2025-12-31',
            'status' => 'aktif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('prodi')->insert([
            'id' => $prodiId,
            'kode_prodi' => 'IF01',
            'nama_prodi' => 'Informatika',
            'jenjang_pendidikan' => 'S1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('mahasiswa')->insert([
            'id' => $mahasiswaId,
            'id_prodi' => $prodiId,
            'id_dosen' => null,
            'user_id' => null,
            'nim' => '2025001',
            'nik' => '3173000000000001',
            'nama_mahasiswa' => 'Mahasiswa Uji',
            'jenis_kelamin' => 'L',
            'status' => 'Aktif',
            'angkatan' => 2025,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('krs')->insert([
            'id' => $krsId,
            'id_mahasiswa' => $mahasiswaId,
            'id_semester' => $semesterId,
            'tanggal_pengajuan' => now(),
            'status_approval' => 'approved',
            'approved_by' => null,
            'tanggal_approval' => now(),
            'catatan' => null,
            'total_sks' => 0,
            'is_locked' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'tahun_akademik_id' => $tahunAkademikId,
            'semester_id' => $semesterId,
            'prodi_id' => $prodiId,
            'mahasiswa_id' => $mahasiswaId,
            'krs_id' => $krsId,
        ];
    }

    private function createKrsDetail(array $context, array $detailAttributes, array $courseAttributes = []): array
    {
        $mataKuliahId = (string) Str::uuid();
        $kurikulumId = (string) Str::uuid();
        $kurikulumMataKuliahId = (string) Str::uuid();
        $kelasKuliahId = (string) Str::uuid();
        $krsDetailId = (string) Str::uuid();

        $kodeMk = $courseAttributes['kode_mk'] ?? 'MK001';
        $namaMk = $courseAttributes['nama_mk'] ?? 'Pemrograman';
        $sks = $courseAttributes['sks'] ?? 3;

        DB::table('mata_kuliah')->insert([
            'id' => $mataKuliahId,
            'id_prodi' => $context['prodi_id'],
            'kode_mk' => $kodeMk,
            'nama_mk' => $namaMk,
            'sks' => $sks,
            'sks_tatap_muka' => $sks,
            'sks_praktikum' => 0,
            'sks_praktek_lapangan' => 0,
            'sks_simulasi' => 0,
            'jenis_mk' => 'wajib_prodi',
            'kelompok_mk' => 'MKK',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('kurikulum')->insert([
            'id' => $kurikulumId,
            'id_prodi' => $context['prodi_id'],
            'id_semester' => $context['semester_id'],
            'nama_kurikulum' => 'Kurikulum ' . $kodeMk,
            'jumlah_sks_lulus' => 144,
            'jumlah_sks_wajib' => 140,
            'jumlah_sks_pilihan' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('kurikulum_mata_kuliah')->insert([
            'id' => $kurikulumMataKuliahId,
            'id_kurikulum' => $kurikulumId,
            'id_mata_kuliah' => $mataKuliahId,
            'semester_ke' => 1,
            'status_mk' => 'wajib',
            'is_wajib' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('kelas_kuliah')->insert([
            'id' => $kelasKuliahId,
            'id_prodi' => $context['prodi_id'],
            'id_kurikulum_mata_kuliah' => $kurikulumMataKuliahId,
            'id_semester' => $context['semester_id'],
            'nama_kelas' => 'A-' . $kodeMk,
            'bahasan' => null,
            'lingkup' => null,
            'mode_kuliah' => null,
            'tanggal_mulai_efektif' => null,
            'tanggal_akhir_efektif' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('krs_detail')->insert([
            'id' => $krsDetailId,
            'id_krs' => $context['krs_id'],
            'id_kelas_kuliah' => $kelasKuliahId,
            'status' => $detailAttributes['status'],
            'catatan' => $detailAttributes['catatan'] ?? null,
            'nilai_akhir' => $detailAttributes['nilai_akhir'],
            'nilai_huruf' => $detailAttributes['nilai_huruf'],
            'bobot_nilai' => $detailAttributes['bobot_nilai'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'mata_kuliah_id' => $mataKuliahId,
            'kelas_kuliah_id' => $kelasKuliahId,
            'krs_detail_id' => $krsDetailId,
        ];
    }

    private function rebuildAcademicSchema(): void
    {
        Schema::dropIfExists('khs_detail');
        Schema::dropIfExists('khs');
        Schema::dropIfExists('krs_detail');
        Schema::dropIfExists('krs');
        Schema::dropIfExists('kelas_kuliah');
        Schema::dropIfExists('kurikulum_mata_kuliah');
        Schema::dropIfExists('kurikulum');
        Schema::dropIfExists('mata_kuliah');
        Schema::dropIfExists('mahasiswa');
        Schema::dropIfExists('semester');
        Schema::dropIfExists('tahun_akademik');
        Schema::dropIfExists('prodi');

        Schema::create('tahun_akademik', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tahun_akademik', 20);
            $table->boolean('status_aktif')->default(false);
            $table->timestamps();
        });

        Schema::create('semester', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_tahun_akademik');
            $table->string('nama_semester');
            $table->string('kode_semester');
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('prodi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode_prodi', 10)->unique();
            $table->string('nama_prodi', 100);
            $table->string('jenjang_pendidikan', 100);
            $table->timestamps();
        });

        Schema::create('mahasiswa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_prodi');
            $table->uuid('id_dosen')->nullable();
            $table->uuid('user_id')->nullable();
            $table->uuid('id_kurikulum')->nullable();
            $table->string('nim')->unique()->nullable();
            $table->string('nik')->unique()->nullable();
            $table->string('nama_mahasiswa');
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();
            $table->string('status')->nullable();
            $table->integer('angkatan')->nullable();
            $table->timestamps();
        });

        Schema::create('mata_kuliah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_prodi');
            $table->string('kode_mk', 20);
            $table->string('nama_mk');
            $table->unsignedTinyInteger('sks')->default(0);
            $table->unsignedTinyInteger('sks_tatap_muka')->default(0);
            $table->unsignedTinyInteger('sks_praktikum')->default(0);
            $table->unsignedTinyInteger('sks_praktek_lapangan')->default(0);
            $table->unsignedTinyInteger('sks_simulasi')->default(0);
            $table->string('jenis_mk');
            $table->string('kelompok_mk');
            $table->timestamps();
            $table->unique(['id_prodi', 'kode_mk']);
        });

        Schema::create('kurikulum', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_prodi');
            $table->uuid('id_semester');
            $table->string('nama_kurikulum');
            $table->unsignedSmallInteger('jumlah_sks_lulus');
            $table->unsignedSmallInteger('jumlah_sks_wajib');
            $table->unsignedSmallInteger('jumlah_sks_pilihan');
            $table->timestamps();
        });

        Schema::create('kurikulum_mata_kuliah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_kurikulum');
            $table->uuid('id_mata_kuliah')->nullable();
            $table->unsignedTinyInteger('semester_ke')->nullable();
            $table->string('status_mk')->nullable();
            $table->boolean('is_wajib')->default(true)->nullable();
            $table->timestamps();
        });

        Schema::create('kelas_kuliah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_prodi');
            $table->uuid('id_kurikulum_mata_kuliah');
            $table->uuid('id_semester');
            $table->string('nama_kelas');
            $table->string('bahasan')->nullable();
            $table->string('lingkup')->nullable();
            $table->string('mode_kuliah')->nullable();
            $table->date('tanggal_mulai_efektif')->nullable();
            $table->date('tanggal_akhir_efektif')->nullable();
            $table->timestamps();
        });

        Schema::create('krs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_mahasiswa');
            $table->uuid('id_semester');
            $table->dateTime('tanggal_pengajuan');
            $table->string('status_approval')->default('pending');
            $table->uuid('approved_by')->nullable();
            $table->dateTime('tanggal_approval')->nullable();
            $table->text('catatan')->nullable();
            $table->integer('total_sks')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
        });

        Schema::create('krs_detail', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_krs');
            $table->uuid('id_kelas_kuliah');
            $table->string('status')->default('terdaftar');
            $table->text('catatan')->nullable();
            $table->decimal('nilai_akhir', 5, 2)->nullable();
            $table->string('nilai_huruf', 2)->nullable();
            $table->decimal('bobot_nilai', 3, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('khs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_mahasiswa');
            $table->uuid('id_semester');
            $table->unsignedInteger('total_sks_diambil')->default(0);
            $table->unsignedInteger('total_sks_lulus')->default(0);
            $table->decimal('ips', 4, 2)->default(0);
            $table->decimal('ipk', 4, 2)->default(0);
            $table->boolean('is_final')->default(false);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            $table->unique(['id_mahasiswa', 'id_semester']);
        });

        Schema::create('khs_detail', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_khs');
            $table->uuid('id_krs_detail')->nullable();
            $table->uuid('id_kelas_kuliah')->nullable();
            $table->uuid('id_mata_kuliah')->nullable();
            $table->string('kode_mk', 20)->nullable();
            $table->string('nama_mk')->nullable();
            $table->unsignedTinyInteger('sks')->default(0);
            $table->decimal('nilai_akhir', 5, 2)->nullable();
            $table->string('nilai_huruf', 2)->nullable();
            $table->decimal('bobot_nilai', 3, 2)->nullable();
            $table->string('status')->default('terdaftar');
            $table->timestamps();
        });
    }

    private function callKhsPreview(array $query): \Illuminate\Http\JsonResponse
    {
        $controller = app(KHSController::class);
        $request = Request::create('/api/v1/khs/preview/semester', 'GET', $query);

        return $controller->preview($request);
    }

    private function callKhsGenerate(array $payload): \Illuminate\Http\JsonResponse
    {
        $controller = app(KHSController::class);
        $request = Request::create('/api/v1/khs/generate', 'POST', $payload);

        return $controller->generate($request);
    }
}
