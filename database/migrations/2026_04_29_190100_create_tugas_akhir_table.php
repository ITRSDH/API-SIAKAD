<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tugas_akhir', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_mahasiswa')->constrained('mahasiswa', 'id')->cascadeOnDelete();
            $table->foreignUuid('id_kurikulum')->nullable()->constrained('kurikulum', 'id')->nullOnDelete();
            $table->string('jenis_tugas_akhir', 50);
            $table->string('judul');
            $table->text('topik')->nullable();
            $table->enum('status', ['draft', 'pengajuan', 'bimbingan', 'ujian', 'revisi', 'lulus', 'tidak_lulus', 'dibatalkan'])->default('draft');
            $table->date('tanggal_pengajuan')->nullable();
            $table->date('tanggal_mulai_bimbingan')->nullable();
            $table->date('tanggal_lulus')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['id_mahasiswa', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tugas_akhir');
    }
};
