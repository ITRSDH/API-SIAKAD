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
        Schema::create('mahasiswa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_prodi')->constrained('prodi', 'id');
            $table->foreignUuid('id_kelas_pararel')->nullable()->constrained('kelas_pararel', 'id'); // bisa null saat belum masuk kelas
            $table->foreignUuid('id_dosen')->nullable()->constrained('dosen', 'id'); // dosen wali
            $table->foreignUuid('user_id')->nullable()->constrained('users', 'id');
            $table->string('nim')->unique();
            $table->string('nama_mahasiswa');
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->date('tanggal_lahir')->nullable();
            $table->text('alamat')->nullable();
            $table->string('no_hp')->nullable();
            // $table->string('email')->unique()->nullable();
            $table->string('asal_sekolah')->nullable();
            $table->string('nama_orang_tua')->nullable();
            $table->string('no_hp_orang_tua')->nullable();
            $table->enum('status', ['Aktif', 'Cuti', 'DO', 'Lulus'])->default('Aktif');
            $table->integer('angkatan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mahasiswa');
    }
};
