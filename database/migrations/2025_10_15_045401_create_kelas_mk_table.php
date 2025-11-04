<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kelas_mk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_mk')->constrained('mata_kuliah', 'id');
            $table->uuid('id_kelas_pararel')->constrained('kelas_pararel', 'id');
            $table->uuid('id_semester')->constrained('semester', 'id');
            $table->uuid('id_jenis_kelas')->constrained('jenis_kelas', 'id');
            $table->string('kode_kelas_mk')->unique(); // Misal: IF101-A
            $table->integer('kuota')->default(30);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kelas_mk');
    }
};
