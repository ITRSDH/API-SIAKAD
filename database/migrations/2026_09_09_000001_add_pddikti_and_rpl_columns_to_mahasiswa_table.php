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
        Schema::table('mahasiswa', function (Blueprint $table) {
            // 1. Biodata Tambahan
            $table->string('nisn', 20)->nullable()->after('nik');
            $table->string('kewarganegaraan', 10)->default('ID')->nullable()->after('nisn');
            $table->string('npwp', 30)->nullable()->after('kewarganegaraan');

            // 2. Alamat & Kontak Detail
            $table->text('alamat_jalan')->nullable()->after('alamat');
            $table->string('rt', 5)->nullable()->after('alamat_jalan');
            $table->string('rw', 5)->nullable()->after('rt');
            $table->string('dusun', 100)->nullable()->after('rw');
            $table->string('kelurahan', 100)->nullable()->after('dusun');
            $table->string('kode_pos', 10)->nullable()->after('kelurahan');
            $table->string('id_wilayah', 20)->nullable()->after('kode_pos');
            $table->string('jenis_tinggal', 50)->nullable()->after('id_wilayah');
            $table->string('alat_transportasi', 50)->nullable()->after('jenis_tinggal');
            $table->string('handphone', 25)->nullable()->after('alat_transportasi');
            $table->string('email_pribadi', 100)->nullable()->after('handphone');

            // 3. Data Orang Tua & Wali
            $table->string('nama_ibu_kandung', 150)->nullable()->after('email_pribadi');
            $table->string('nik_ibu', 20)->nullable()->after('nama_ibu_kandung');
            $table->date('tanggal_lahir_ibu')->nullable()->after('nik_ibu');
            $table->string('pendidikan_ibu', 50)->nullable()->after('tanggal_lahir_ibu');
            $table->string('pekerjaan_ibu', 50)->nullable()->after('pendidikan_ibu');
            $table->string('penghasilan_ibu', 50)->nullable()->after('pekerjaan_ibu');

            $table->string('nama_ayah', 150)->nullable()->after('penghasilan_ibu');
            $table->string('nik_ayah', 20)->nullable()->after('nama_ayah');
            $table->date('tanggal_lahir_ayah')->nullable()->after('nik_ayah');
            $table->string('pendidikan_ayah', 50)->nullable()->after('tanggal_lahir_ayah');
            $table->string('pekerjaan_ayah', 50)->nullable()->after('pendidikan_ayah');
            $table->string('penghasilan_ayah', 50)->nullable()->after('pekerjaan_ayah');

            $table->string('nama_wali', 150)->nullable()->after('penghasilan_ayah');
            $table->string('pendidikan_wali', 50)->nullable()->after('nama_wali');
            $table->string('pekerjaan_wali', 50)->nullable()->after('pendidikan_wali');
            $table->string('penghasilan_wali', 50)->nullable()->after('pekerjaan_wali');

            // 4. Akademik Masuk & Pembeda Reguler vs RPL
            $table->enum('jenis_pendaftaran', ['Reguler', 'Pindahan', 'RPL'])->default('Reguler')->after('penghasilan_wali');
            $table->string('jalur_masuk', 100)->nullable()->after('jenis_pendaftaran');
            $table->string('sistem_pembiayaan', 100)->nullable()->after('jalur_masuk');
            $table->string('id_periode_masuk', 10)->nullable()->after('sistem_pembiayaan');
            $table->string('perguruan_tinggi_asal', 200)->nullable()->after('id_periode_masuk');
            $table->string('prodi_asal', 150)->nullable()->after('perguruan_tinggi_asal');
            $table->unsignedInteger('sks_diakui')->default(0)->after('prodi_asal');

            // 5. Data Khusus / KPS & Sinkronisasi PDDikti
            $table->boolean('penerima_kps')->default(false)->after('sks_diakui');
            $table->string('nomor_kps', 50)->nullable()->after('penerima_kps');
            $table->string('kebutuhan_khusus', 100)->nullable()->after('nomor_kps');

            $table->string('id_mahasiswa_pddikti', 36)->nullable()->after('kebutuhan_khusus');
            $table->string('id_registrasi_mahasiswa_pddikti', 36)->nullable()->after('id_mahasiswa_pddikti');
            $table->enum('sync_status', ['belum', 'antre', 'sukses', 'gagal'])->default('belum')->after('id_registrasi_mahasiswa_pddikti');
            $table->timestamp('last_synced_at')->nullable()->after('sync_status');
            $table->text('last_sync_error')->nullable()->after('last_synced_at');

            // Indeks pendukung
            $table->index('jenis_pendaftaran');
            $table->index('sync_status');
            $table->index('id_mahasiswa_pddikti');
            $table->index('id_registrasi_mahasiswa_pddikti');
        });

        // Sinkronisasi data riil: deteksi mahasiswa berakhiran 'B'/'b' pada NIM sebagai RPL
        \Illuminate\Support\Facades\DB::table('mahasiswa')
            ->where(function ($query) {
                $query->where('nim', 'LIKE', '%B')
                    ->orWhere('nim', 'LIKE', '%b');
            })
            ->update([
                'jenis_pendaftaran' => 'RPL',
                'jalur_masuk' => \Illuminate\Support\Facades\DB::raw("CASE WHEN jalur_masuk IS NULL OR jalur_masuk = '' OR jalur_masuk = 'Reguler' THEN 'RPL' ELSE jalur_masuk END"),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->dropIndex(['jenis_pendaftaran']);
            $table->dropIndex(['sync_status']);
            $table->dropIndex(['id_mahasiswa_pddikti']);
            $table->dropIndex(['id_registrasi_mahasiswa_pddikti']);

            $table->dropColumn([
                'nisn',
                'kewarganegaraan',
                'npwp',
                'alamat_jalan',
                'rt',
                'rw',
                'dusun',
                'kelurahan',
                'kode_pos',
                'id_wilayah',
                'jenis_tinggal',
                'alat_transportasi',
                'handphone',
                'email_pribadi',
                'nama_ibu_kandung',
                'nik_ibu',
                'tanggal_lahir_ibu',
                'pendidikan_ibu',
                'pekerjaan_ibu',
                'penghasilan_ibu',
                'nama_ayah',
                'nik_ayah',
                'tanggal_lahir_ayah',
                'pendidikan_ayah',
                'pekerjaan_ayah',
                'penghasilan_ayah',
                'nama_wali',
                'pendidikan_wali',
                'pekerjaan_wali',
                'penghasilan_wali',
                'jenis_pendaftaran',
                'jalur_masuk',
                'sistem_pembiayaan',
                'id_periode_masuk',
                'perguruan_tinggi_asal',
                'prodi_asal',
                'sks_diakui',
                'penerima_kps',
                'nomor_kps',
                'kebutuhan_khusus',
                'id_mahasiswa_pddikti',
                'id_registrasi_mahasiswa_pddikti',
                'sync_status',
                'last_synced_at',
                'last_sync_error',
            ]);
        });
    }
};
