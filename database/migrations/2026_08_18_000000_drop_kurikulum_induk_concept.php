<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migrasi terminal untuk database yang sudah dibangun dengan konsep
     * "kurikulum induk". Menghapus indirection:
     *
     *   - tabel  kurikulum_induk             (master / tahun kurikulum)
     *   - tabel  ref_jenis_kurikulum         (referensi jenis kurikulum)
     *   - tabel  riwayat_kurikulum_mahasiswa (histori penugasan mahasiswa)
     *   - kolom  kurikulum.id_kurikulum_induk
     *
     * Tabel kurikulum bertahan sebagai satu-satunya struktur mata kuliah
     * yang dipakai untuk membangun KRS / KHS.
     */
    public function up(): void
    {
        if (Schema::hasColumn('kurikulum', 'id_kurikulum_induk')) {
            Schema::table('kurikulum', function (Blueprint $table) {
                $table->dropForeign(['id_kurikulum_induk']);
                $table->dropColumn('id_kurikulum_induk');
            });
        }

        Schema::dropIfExists('riwayat_kurikulum_mahasiswa');
        Schema::dropIfExists('kurikulum_induk');
        Schema::dropIfExists('ref_jenis_kurikulum');
    }

    public function down(): void
    {
        Schema::create('ref_jenis_kurikulum', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode_jenis', 20)->unique();
            $table->string('nama_jenis_kurikulum', 150);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('kurikulum_induk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_prodi')->constrained('prodi')->cascadeOnDelete();
            $table->foreignUuid('id_jenis_kurikulum')->nullable()
                ->after('id_prodi')
                ->references('id')->on('ref_jenis_kurikulum')->restrictOnDelete();
            $table->string('nama_kurikulum');
            $table->string('tahun_kurikulum', 4)->nullable()->after('nama_kurikulum');
            $table->string('kode_kurikulum', 50)->nullable()->after('tahun_kurikulum');
            $table->boolean('is_aktif')->default(false)->after('kode_kurikulum');
            $table->timestamps();

            $table->unique('kode_kurikulum', 'kurikulum_induk_kode_unique');
            $table->unique(
                ['id_prodi', 'id_jenis_kurikulum', 'tahun_kurikulum'],
                'kurikulum_induk_prodi_jenis_tahun_unique'
            );
        });

        Schema::create('riwayat_kurikulum_mahasiswa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_mahasiswa')->constrained('mahasiswa')->cascadeOnDelete();
            $table->foreignUuid('id_kurikulum')->constrained('kurikulum')->restrictOnDelete();
            $table->foreignUuid('id_kurikulum_induk')->nullable()
                ->after('id_kurikulum')
                ->references('id')->on('kurikulum_induk')->nullOnDelete();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->boolean('is_active')->default(false);
            $table->text('catatan')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['id_mahasiswa', 'is_active'], 'riwayat_kurikulum_mahasiswa_active_idx');
        });

        Schema::table('kurikulum', function (Blueprint $table) {
            $table->foreignUuid('id_kurikulum_induk')
                ->nullable()
                ->after('id_prodi')
                ->references('id')->on('kurikulum_induk')->nullOnDelete();
        });
    }
};
