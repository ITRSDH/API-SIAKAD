<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('profile_kampus', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('judul')->default('Profil Kampus');
            $table->longText('deskripsi')->nullable();
            $table->longText('visi')->nullable();
            $table->longText('misi')->nullable();
            $table->string('struktur_image')->nullable(); // gambar struktur organisasi
            $table->longText('fasilitas')->nullable(); // JSON atau teks panjang
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_kampus');
    }
};
