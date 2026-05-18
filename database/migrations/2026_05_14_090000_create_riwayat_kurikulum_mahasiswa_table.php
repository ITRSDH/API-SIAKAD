<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_kurikulum_mahasiswa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('id_mahasiswa')->constrained('mahasiswa')->cascadeOnDelete();
            $table->foreignUuid('id_kurikulum')->constrained('kurikulum')->restrictOnDelete();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->boolean('is_active')->default(false);
            $table->text('catatan')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['id_mahasiswa', 'is_active'], 'riwayat_kurikulum_mahasiswa_active_idx');
        });

        $mahasiswaList = DB::table('mahasiswa')
            ->select('id', 'id_kurikulum', 'tanggal_masuk', 'created_at')
            ->whereNotNull('id_kurikulum')
            ->get();

        foreach ($mahasiswaList as $mahasiswa) {
            DB::table('riwayat_kurikulum_mahasiswa')->insert([
                'id' => (string) Str::uuid(),
                'id_mahasiswa' => $mahasiswa->id,
                'id_kurikulum' => $mahasiswa->id_kurikulum,
                'tanggal_mulai' => $mahasiswa->tanggal_masuk
                    ?: ($mahasiswa->created_at ? date('Y-m-d', strtotime((string) $mahasiswa->created_at)) : now()->toDateString()),
                'tanggal_selesai' => null,
                'is_active' => true,
                'catatan' => 'Riwayat awal dibentuk dari data mahasiswa existing',
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_kurikulum_mahasiswa');
    }
};
