<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tugas_akhir_ujian', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_tugas_akhir')->constrained('tugas_akhir', 'id')->cascadeOnDelete();
            $table->enum('jenis_ujian', ['proposal', 'hasil', 'akhir']);
            $table->date('tanggal_ujian');
            $table->decimal('nilai_ujian', 5, 2)->nullable();
            $table->enum('keputusan', ['lulus', 'revisi', 'tidak_lulus']);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tugas_akhir_ujian');
    }
};
