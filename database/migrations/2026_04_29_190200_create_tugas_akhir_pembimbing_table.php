<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tugas_akhir_pembimbing', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_tugas_akhir')->constrained('tugas_akhir', 'id')->cascadeOnDelete();
            $table->foreignUuid('id_dosen')->constrained('dosen', 'id')->cascadeOnDelete();
            $table->enum('peran', ['pembimbing_1', 'pembimbing_2', 'co_pembimbing']);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['id_tugas_akhir', 'peran']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tugas_akhir_pembimbing');
    }
};
