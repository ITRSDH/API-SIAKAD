<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penilaian_kelas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_kelas_kuliah')->constrained('kelas_kuliah')->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignUuid('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignUuid('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();

            $table->unique('id_kelas_kuliah');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penilaian_kelas');
    }
};
