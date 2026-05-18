<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('konversi_mata_kuliah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_kurikulum_asal')->constrained('kurikulum')->restrictOnDelete();
            $table->foreignUuid('id_kurikulum_tujuan')->constrained('kurikulum')->restrictOnDelete();
            $table->foreignUuid('id_mata_kuliah_asal')->constrained('mata_kuliah')->restrictOnDelete();
            $table->foreignUuid('id_mata_kuliah_tujuan')->constrained('mata_kuliah')->restrictOnDelete();
            $table->enum('status_konversi', ['diakui', 'wajib_ulang', 'pilihan_bebas'])->default('diakui');
            $table->decimal('min_bobot_nilai', 4, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['id_kurikulum_asal', 'id_kurikulum_tujuan', 'id_mata_kuliah_asal', 'id_mata_kuliah_tujuan'],
                'konversi_mata_kuliah_unique_rule'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('konversi_mata_kuliah');
    }
};
