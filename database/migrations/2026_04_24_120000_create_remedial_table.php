<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remedial', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_krs_detail')->constrained('krs_detail', 'id')->cascadeOnDelete();
            $table->foreignUuid('id_kelas_kuliah')->nullable()->constrained('kelas_kuliah', 'id')->nullOnDelete();
            $table->unsignedTinyInteger('attempt_ke')->default(1);
            $table->date('tanggal_remedial')->nullable();
            $table->decimal('nilai_sebelum', 5, 2)->nullable();
            $table->decimal('nilai_remedial', 5, 2)->nullable();
            $table->decimal('nilai_final', 5, 2)->nullable();
            $table->string('nilai_huruf_final', 2)->nullable();
            $table->decimal('bobot_nilai_final', 3, 2)->nullable();
            $table->enum('status', ['draft', 'published', 'cancelled'])->default('draft');
            $table->text('catatan')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['id_krs_detail', 'attempt_ke']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remedial');
    }
};
