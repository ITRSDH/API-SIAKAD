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
        Schema::create('pddikti_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Toggle Saklar Utama
            $table->boolean('sync_enabled')->default(false);
            $table->enum('sync_mode', ['manual', 'auto'])->default('manual');

            // Granular Switch per Entitas
            $table->boolean('auto_sync_mahasiswa')->default(false);
            $table->boolean('auto_sync_krs')->default(false);
            $table->boolean('auto_sync_nilai')->default(false);

            // Kredensial & Endpoint Feeder
            $table->string('feeder_url')->nullable();
            $table->string('feeder_username')->nullable();
            $table->text('feeder_password')->nullable();
            $table->text('feeder_token')->nullable();

            // Status Koneksi Terakhir
            $table->timestamp('last_connected_at')->nullable();
            $table->text('last_error_message')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pddikti_settings');
    }
};
