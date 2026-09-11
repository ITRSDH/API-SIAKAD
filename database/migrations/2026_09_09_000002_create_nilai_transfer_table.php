<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nilai_transfer', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_mahasiswa')->constrained('mahasiswa', 'id')->cascadeOnDelete();
            $table->foreignUuid('id_mata_kuliah')->constrained('mata_kuliah', 'id')->restrictOnDelete();

            // Data Mata Kuliah Asal (Transkrip D3 / Kampus Asal)
            $table->string('kode_mata_kuliah_asal', 50)->nullable();
            $table->string('nama_mata_kuliah_asal', 150);
            $table->decimal('sks_asal', 4, 2)->default(0);
            $table->string('nilai_huruf_asal', 5)->nullable();

            // Data Penyetaraan di Kampus Kita (S1 STIKES)
            $table->decimal('sks_diakui', 4, 2);
            $table->decimal('nilai_angka_diakui', 5, 2)->nullable();
            $table->string('nilai_huruf_diakui', 5);
            $table->decimal('nilai_indeks_diakui', 4, 2)->default(0);

            // Integrasi PDDikti
            $table->string('id_nilai_transfer_pddikti', 36)->nullable();
            $table->enum('sync_status', ['belum', 'antre', 'sukses', 'gagal'])->default('belum');
            $table->timestamp('last_synced_at')->nullable();

            $table->text('keterangan')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->timestamps();

            $table->index('id_mahasiswa');
            $table->index('id_mata_kuliah');
            $table->index('sync_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nilai_transfer');
    }
};
