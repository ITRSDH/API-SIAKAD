<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode_wisuda', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama_periode', 150);
            $table->date('tanggal_mulai_pendaftaran')->nullable();
            $table->date('tanggal_selesai_pendaftaran')->nullable();
            $table->date('tanggal_wisuda');
            $table->string('lokasi')->nullable();
            $table->enum('status', ['draft', 'dibuka', 'ditutup', 'selesai'])->default('draft');
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periode_wisuda');
    }
};
