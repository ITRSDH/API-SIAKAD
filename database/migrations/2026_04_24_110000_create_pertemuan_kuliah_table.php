<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pertemuan_kuliah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_kelas_kuliah')->constrained('kelas_kuliah', 'id')->cascadeOnDelete();
            $table->unsignedTinyInteger('pertemuan_ke');
            $table->date('tanggal_pertemuan')->nullable();
            $table->string('materi')->nullable();
            $table->text('catatan')->nullable();
            $table->enum('status', ['draft', 'selesai', 'dibatalkan'])->default('draft');
            $table->timestamps();

            $table->unique(['id_kelas_kuliah', 'pertemuan_ke']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pertemuan_kuliah');
    }
};
