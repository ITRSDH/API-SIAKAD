<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('krs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('id_mahasiswa');
            $table->uuid('id_semester');
            $table->datetime('tanggal_pengajuan');
            $table->enum('status_approval', ['pending', 'approved', 'rejected', 'revised'])->default('pending');
            $table->uuid('approved_by')->nullable();
            $table->datetime('tanggal_approval')->nullable();
            $table->text('catatan')->nullable();
            $table->integer('total_sks')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->unique(['id_mahasiswa', 'id_semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('krs');
    }
};
