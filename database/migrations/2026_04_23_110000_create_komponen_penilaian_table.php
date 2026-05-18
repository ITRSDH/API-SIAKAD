<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('komponen_penilaian', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_kelas_kuliah')->constrained('kelas_kuliah', 'id')->cascadeOnDelete();
            $table->string('nama');
            $table->decimal('bobot', 5, 2);
            $table->unsignedInteger('urutan')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('komponen_penilaian');
    }
};
