<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transkrip', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_mahasiswa')->unique()->constrained('mahasiswa', 'id')->cascadeOnDelete();
            $table->unsignedInteger('total_sks_lulus')->default(0);
            $table->decimal('ipk', 4, 2)->default(0);
            $table->boolean('is_final')->default(false);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transkrip');
    }
};
