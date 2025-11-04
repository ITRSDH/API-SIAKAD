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
        Schema::create('pembayaran_mahasiswa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_mahasiswa')->constrained('mahasiswa', 'id');
            $table->foreignUuid('id_jenis_pembayaran')->constrained('jenis_pembayaran', 'id');
            $table->date('tanggal_bayar');
            $table->integer('jumlah_bayar'); // dalam satuan rupiah
            $table->enum('status_pembayaran', ['Lunas', 'Belum Lunas', 'Dibatalkan'])->default('Belum Lunas');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayaran_mahasiswa');
    }
};
