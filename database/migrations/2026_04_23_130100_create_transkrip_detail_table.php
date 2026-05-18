<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transkrip_detail', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_transkrip')->constrained('transkrip', 'id')->cascadeOnDelete();
            $table->foreignUuid('id_khs_detail')->nullable()->constrained('khs_detail', 'id')->nullOnDelete();
            $table->foreignUuid('id_krs_detail')->nullable()->constrained('krs_detail', 'id')->nullOnDelete();
            $table->string('kode_mk', 20)->nullable();
            $table->string('nama_mk')->nullable();
            $table->unsignedTinyInteger('sks')->default(0);
            $table->decimal('nilai_akhir', 5, 2)->nullable();
            $table->string('nilai_huruf', 2)->nullable();
            $table->decimal('bobot_nilai', 3, 2)->nullable();
            $table->enum('status', ['lulus', 'tidak_lulus'])->default('lulus');
            $table->string('semester_label')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transkrip_detail');
    }
};
