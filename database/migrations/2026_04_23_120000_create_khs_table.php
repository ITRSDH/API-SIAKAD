<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('khs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_mahasiswa')->constrained('mahasiswa', 'id')->cascadeOnDelete();
            $table->foreignUuid('id_semester')->constrained('semester', 'id')->cascadeOnDelete();
            $table->unsignedInteger('total_sks_diambil')->default(0);
            $table->unsignedInteger('total_sks_lulus')->default(0);
            $table->decimal('ips', 4, 2)->default(0);
            $table->decimal('ipk', 4, 2)->default(0);
            $table->boolean('is_final')->default(false);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['id_mahasiswa', 'id_semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('khs');
    }
};
