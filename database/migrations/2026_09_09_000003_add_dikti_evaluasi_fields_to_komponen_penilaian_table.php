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
        Schema::table('komponen_penilaian', function (Blueprint $table) {
            $table->foreignUuid('id_indikator_kinerja_cpl')
                ->nullable()
                ->after('id_kelas_kuliah')
                ->constrained('indikator_kinerja_cpl', 'id')
                ->nullOnDelete();

            $table->enum('jenis_evaluasi_dikti', [
                'Aktivitas Partisipatif',
                'Hasil Proyek',
                'Kognitif/Evaluasi Akademik',
                'Lainnya',
            ])->default('Kognitif/Evaluasi Akademik')->after('nama');

            $table->string('id_komponen_evaluasi_pddikti', 36)->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('komponen_penilaian', function (Blueprint $table) {
            $table->dropForeign(['id_indikator_kinerja_cpl']);
            $table->dropColumn([
                'id_indikator_kinerja_cpl',
                'jenis_evaluasi_dikti',
                'id_komponen_evaluasi_pddikti',
            ]);
        });
    }
};
