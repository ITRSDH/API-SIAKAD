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
        Schema::create('nilai', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_kelas_mk')->constrained('kelas_mk', 'id');
            $table->foreignUuid('id_mahasiswa')->constrained('mahasiswa', 'id');
            $table->foreignUuid('id_semester')->constrained('semester', 'id');
            $table->decimal('nilai_angka', 5, 2); // contoh: 85.50
            $table->string('nilai_huruf', 2); // contoh: A, A-
            $table->decimal('bobot', 3, 2); // contoh: 4.00, 3.70
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nilai');
    }
};
