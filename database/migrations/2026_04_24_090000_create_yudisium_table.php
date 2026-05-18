<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yudisium', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_mahasiswa')->unique()->constrained('mahasiswa', 'id')->cascadeOnDelete();
            $table->foreignUuid('id_transkrip')->nullable()->constrained('transkrip', 'id')->nullOnDelete();
            $table->foreignUuid('id_kurikulum')->nullable()->constrained('kurikulum', 'id')->nullOnDelete();
            $table->unsignedInteger('target_sks_lulus')->default(0);
            $table->unsignedInteger('total_sks_lulus')->default(0);
            $table->decimal('ipk', 4, 2)->default(0);
            $table->enum('status', ['memenuhi', 'belum_memenuhi'])->default('belum_memenuhi');
            $table->string('predikat_lulus', 50)->nullable();
            $table->date('tanggal_yudisium')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yudisium');
    }
};
