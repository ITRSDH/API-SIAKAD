<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelulusan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_mahasiswa')->unique()->constrained('mahasiswa', 'id')->cascadeOnDelete();
            $table->foreignUuid('id_yudisium')->nullable()->constrained('yudisium', 'id')->nullOnDelete();
            $table->date('tanggal_lulus')->nullable();
            $table->string('nomor_sk', 100)->nullable();
            $table->string('nomor_ijazah', 100)->nullable();
            $table->enum('status', ['draft', 'ditetapkan'])->default('draft');
            $table->text('catatan')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelulusan');
    }
};
