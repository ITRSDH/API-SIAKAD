<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User; // Pastikan model User diimport

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cache sebelum seeding
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // --- Master Data Permissions ---
        // Jenjang Pendidikan
        Permission::create(['name' => 'view jenjang pendidikan']);
        Permission::create(['name' => 'create jenjang pendidikan']);
        Permission::create(['name' => 'edit jenjang pendidikan']);
        Permission::create(['name' => 'delete jenjang pendidikan']);

        // Prodi
        Permission::create(['name' => 'view prodi']);
        Permission::create(['name' => 'create prodi']);
        Permission::create(['name' => 'edit prodi']);
        Permission::create(['name' => 'delete prodi']);

        // Tahun Akademik
        Permission::create(['name' => 'view tahun akademik']);
        Permission::create(['name' => 'create tahun akademik']);
        Permission::create(['name' => 'edit tahun akademik']);
        Permission::create(['name' => 'delete tahun akademik']);

        // Semester
        Permission::create(['name' => 'view semester']);
        Permission::create(['name' => 'create semester']);
        Permission::create(['name' => 'edit semester']);
        Permission::create(['name' => 'delete semester']);

        // Kurikulum
        Permission::create(['name' => 'view kurikulum']);
        Permission::create(['name' => 'create kurikulum']);
        Permission::create(['name' => 'edit kurikulum']);
        Permission::create(['name' => 'delete kurikulum']);

        // Mata Kuliah
        Permission::create(['name' => 'view mata kuliah']);
        Permission::create(['name' => 'create mata kuliah']);
        Permission::create(['name' => 'edit mata kuliah']);
        Permission::create(['name' => 'delete mata kuliah']);

        // Jenis Kelas
        Permission::create(['name' => 'view jenis kelas']);
        Permission::create(['name' => 'create jenis kelas']);
        Permission::create(['name' => 'edit jenis kelas']);
        Permission::create(['name' => 'delete jenis kelas']);

        // Kelas Pararel
        Permission::create(['name' => 'view kelas pararel']);
        Permission::create(['name' => 'create kelas pararel']);
        Permission::create(['name' => 'edit kelas pararel']);
        Permission::create(['name' => 'delete kelas pararel']);

        // Ruang
        Permission::create(['name' => 'view ruang']);
        Permission::create(['name' => 'create ruang']);
        Permission::create(['name' => 'edit ruang']);
        Permission::create(['name' => 'delete ruang']);

        // Dosen
        Permission::create(['name' => 'view dosen']);
        Permission::create(['name' => 'create dosen']);
        Permission::create(['name' => 'edit dosen']);
        Permission::create(['name' => 'delete dosen']);

        // Mahasiswa
        Permission::create(['name' => 'view mahasiswa']);
        Permission::create(['name' => 'create mahasiswa']);
        Permission::create(['name' => 'edit mahasiswa']);
        Permission::create(['name' => 'delete mahasiswa']);

        // Jenis Pembayaran
        Permission::create(['name' => 'view jenis pembayaran']);
        Permission::create(['name' => 'create jenis pembayaran']);
        Permission::create(['name' => 'edit jenis pembayaran']);
        Permission::create(['name' => 'delete jenis pembayaran']);

        // --- Proses Akademik Permissions ---
        // Kelas MK
        Permission::create(['name' => 'view kelas mk']);
        Permission::create(['name' => 'create kelas mk']);
        Permission::create(['name' => 'edit kelas mk']);
        Permission::create(['name' => 'delete kelas mk']);

        // Jadwal Kuliah
        Permission::create(['name' => 'view jadwal kuliah']);
        Permission::create(['name' => 'create jadwal kuliah']);
        Permission::create(['name' => 'edit jadwal kuliah']);
        Permission::create(['name' => 'delete jadwal kuliah']);

        // Dosen Kelas MK
        Permission::create(['name' => 'view dosen kelas mk']);
        Permission::create(['name' => 'create dosen kelas mk']);
        Permission::create(['name' => 'edit dosen kelas mk']);
        Permission::create(['name' => 'delete dosen kelas mk']);

        // Beban Ajar Dosen
        Permission::create(['name' => 'view beban ajar dosen']);
        Permission::create(['name' => 'create beban ajar dosen']);
        Permission::create(['name' => 'edit beban ajar dosen']);
        Permission::create(['name' => 'delete beban ajar dosen']);

        // Presensi
        Permission::create(['name' => 'view presensi']);
        Permission::create(['name' => 'input presensi']);
        Permission::create(['name' => 'edit presensi']);
        Permission::create(['name' => 'delete presensi']);

        // Nilai
        Permission::create(['name' => 'view nilai']);
        Permission::create(['name' => 'input nilai']);
        Permission::create(['name' => 'edit nilai']);
        Permission::create(['name' => 'delete nilai']);

        // KRS
        Permission::create(['name' => 'view krs']);
        Permission::create(['name' => 'create krs']);
        Permission::create(['name' => 'edit krs']);
        Permission::create(['name' => 'delete krs']);
        Permission::create(['name' => 'approve krs']);
        Permission::create(['name' => 'reject krs']);

        // KRS Detail
        Permission::create(['name' => 'view krs detail']);

        // KHS
        Permission::create(['name' => 'view khs']);
        // KHS Detail
        Permission::create(['name' => 'view khs detail']);

        // Pembayaran Mahasiswa
        Permission::create(['name' => 'view pembayaran mahasiswa']);
        Permission::create(['name' => 'create pembayaran mahasiswa']);
        Permission::create(['name' => 'edit pembayaran mahasiswa']);
        Permission::create(['name' => 'delete pembayaran mahasiswa']);

        // Status Akademik Mahasiswa
        Permission::create(['name' => 'view status akademik mahasiswa']);
        Permission::create(['name' => 'create status akademik mahasiswa']);
        Permission::create(['name' => 'edit status akademik mahasiswa']);
        Permission::create(['name' => 'delete status akademik mahasiswa']);

        // Perwalian
        Permission::create(['name' => 'view perwalian']);
        Permission::create(['name' => 'create perwalian']);
        Permission::create(['name' => 'edit perwalian']);
        Permission::create(['name' => 'delete perwalian']);
        Permission::create(['name' => 'approve perwalian']);
        Permission::create(['name' => 'reject perwalian']);

        // Berkas Mahasiswa
        Permission::create(['name' => 'view berkas mahasiswa']);
        Permission::create(['name' => 'upload berkas mahasiswa']);
        Permission::create(['name' => 'delete berkas mahasiswa']);

        // Alumni
        Permission::create(['name' => 'view alumni']);
        Permission::create(['name' => 'create alumni']);
        Permission::create(['name' => 'edit alumni']);
        Permission::create(['name' => 'delete alumni']);

        // Kelas Mahasiswa
        Permission::create(['name' => 'view kelas mahasiswa']);
        Permission::create(['name' => 'create kelas mahasiswa']);
        Permission::create(['name' => 'edit kelas mahasiswa']);
        Permission::create(['name' => 'delete kelas mahasiswa']);

        // --- Permissions untuk Peran Tambahan ---
        // Permission untuk Pembimbing Tingkat (misalnya akses spesifik ke mahasiswa tingkat tertentu)
        Permission::create(['name' => 'view mahasiswa pembimbing tingkat']);
        Permission::create(['name' => 'input presensi pembimbing tingkat']);
        Permission::create(['name' => 'input nilai pembimbing tingkat']);
        Permission::create(['name' => 'approve krs pembimbing tingkat']);
        Permission::create(['name' => 'approve perwalian pembimbing tingkat']);

        // Permission untuk Ketua Prodi (akses lebih luas di prodi miliknya)
        Permission::create(['name' => 'view mahasiswa kaprodi']);
        Permission::create(['name' => 'create mahasiswa kaprodi']);
        Permission::create(['name' => 'edit mahasiswa kaprodi']);
        Permission::create(['name' => 'delete mahasiswa kaprodi']);
        Permission::create(['name' => 'view dosen kaprodi']);
        Permission::create(['name' => 'create dosen kaprodi']);
        Permission::create(['name' => 'edit dosen kaprodi']);
        Permission::create(['name' => 'delete dosen kaprodi']);
        Permission::create(['name' => 'approve krs kaprodi']);
        Permission::create(['name' => 'approve perwalian kaprodi']);
        Permission::create(['name' => 'manage status akademik kaprodi']);

        // --- Roles ---
        $admin = Role::create(['name' => 'admin']);
        $mahasiswa = Role::create(['name' => 'mahasiswa']);
        $dosen = Role::create(['name' => 'dosen']);
        $kaprodi = Role::create(['name' => 'kaprodi']);
        $keuangan = Role::create(['name' => 'keuangan']);
        $bak = Role::create(['name' => 'bak']);

        // --- Assign Permissions to Roles ---

        // Admin: Semua akses
        $admin->givePermissionTo(Permission::all());

        // Mahasiswa: Akses terbatas ke data dan proses miliknya
        $mahasiswa->givePermissionTo([
            'view mahasiswa', // Melihat profil sendiri
            'edit mahasiswa', // Edit profil sendiri
            'view prodi', // Info prodi
            'view jenjang pendidikan', // Info jenjang
            'view tahun akademik',
            'view semester',
            'view krs', // KRS miliknya
            'view krs detail', // Detail KRS miliknya
            'create krs', // Izin untuk membuat KRS (isi krs)
            'edit krs', // Izin untuk mengedit KRS sebelum disetujui
            'view khs', // KHS miliknya
            'view khs detail', // Detail KHS miliknya
            'view kelas mk', // Kelas MK yang diambil
            'view mata kuliah', // Info MK
            'view jadwal kuliah', // Jadwal miliknya
            'view nilai', // Nilai miliknya
            'view presensi', // Presensi miliknya
            'view pembayaran mahasiswa', // Pembayaran miliknya
            'view jenis pembayaran',
            'view perwalian', // Perwalian miliknya
            'create perwalian', // Membuat permintaan perwalian
            'edit perwalian', // Mengedit permintaan perwalian
            'view dosen', // Info dosen wali
            'view berkas mahasiswa', // Berkas miliknya
            'upload berkas mahasiswa', // Upload berkas miliknya
            'view status akademik mahasiswa', // Status miliknya
            'view kelas mahasiswa', // Kelas pararel miliknya
        ]);

        // Dosen: Akses umum sebagai dosen, ditambahkan secara dinamis berdasarkan peran tambahan
        $dosen->givePermissionTo([
            'view dosen', // Profil sendiri
            'edit dosen', // Edit profil sendiri
            'view prodi', // Info prodi
            'view tahun akademik',
            'view semester',
            'view kelas mk', // Kelas MK yang diajar
            'view mata kuliah', // Info MK
            'view jadwal kuliah', // Jadwal miliknya
            'view mahasiswa', // Mahasiswa di kelasnya / bimbingannya
            'view presensi', // Presensi di kelasnya
            'input presensi', // Input presensi di kelas yang diajar
            'edit presensi', // Edit presensi di kelas yang diajar (jika diperlukan)
            'view nilai', // Nilai di kelasnya
            'input nilai', // Input nilai di kelas yang diajar
            'edit nilai', // Edit nilai di kelas yang diajar (jika diperlukan sebelum final)
            'view beban ajar dosen', // Beban ajar miliknya
            'view perwalian', // Perwalian miliknya
            'create perwalian', // Dosen bisa membuat catatan perwalian
            'edit perwalian', // Edit catatan perwalian
            'view krs', // KRS mahasiswa bimbingan
            'view krs detail', // Detail KRS mahasiswa bimbingan
            // Permission untuk peran tambahan akan ditambahkan secara dinamis
        ]);

        // Kaprodi: Akses ke data dan proses di prodi miliknya (sudah termasuk peran kaprodi)
        $kaprodi->givePermissionTo([
            'view prodi', // Prodi miliknya
            'view jenjang pendidikan', // Info jenjang prodi miliknya
            'view tahun akademik',
            'view semester',
            'view mahasiswa kaprodi', // Mahasiswa di prodi miliknya (menggunakan permission spesifik)
            'view dosen kaprodi', // Dosen di prodi miliknya (menggunakan permission spesifik)
            'view mata kuliah', // MK di kurikulum prodi miliknya
            'view kurikulum', // Kurikulum prodi miliknya
            'view kelas mk', // Kelas MK di prodi miliknya
            'view jadwal kuliah', // Jadwal di prodi miliknya
            'view kelas pararel', // Kelas pararel di prodi miliknya
            'view nilai', // Nilai di prodi miliknya
            'view presensi', // Presensi di prodi miliknya
            'view krs', // KRS di prodi miliknya
            'view krs detail', // Detail KRS di prodi miliknya
            'view khs', // KHS di prodi miliknya
            'view khs detail', // Detail KHS di prodi miliknya
            'view pembayaran mahasiswa', // Pembayaran di prodi miliknya
            'view jenis pembayaran',
            'view beban ajar dosen', // Beban ajar di prodi miliknya
            'view dosen kelas mk', // Penugasan dosen di prodi miliknya
            'view kelas mahasiswa', // Kelas mahasiswa di prodi miliknya
            'view status akademik mahasiswa', // Status di prodi miliknya
            'view perwalian', // Perwalian di prodi miliknya
            'approve perwalian', // Approve perwalian di prodi miliknya
            'reject perwalian', // Reject perwalian di prodi miliknya
            'approve krs', // Approve KRS di prodi miliknya
            'reject krs', // Reject KRS di prodi miliknya
            'manage status akademik kaprodi', // Ubah status di prodi miliknya
            'create mahasiswa kaprodi', // Jika kaprodi bisa tambah/edit mahasiswa
            'edit mahasiswa kaprodi',
            'create dosen kaprodi', // Jika kaprodi bisa tambah/edit dosen
            'edit dosen kaprodi',
        ]);

        // Keuangan: Akses ke data pembayaran
        $keuangan->givePermissionTo([
            'view pembayaran mahasiswa', // Semua pembayaran
            'create pembayaran mahasiswa', // Input pembayaran (misalnya manual)
            'edit pembayaran mahasiswa', // Validasi pembayaran
            'view mahasiswa', // Info mahasiswa terkait pembayaran
            'view jenis pembayaran', // Info jenis pembayaran
            'view prodi', // Filter pembayaran berdasarkan prodi
        ]);

        // BAK: Akses ke proses administrasi akademik utama
        $bak->givePermissionTo([
            'view tahun akademik',
            'view semester',
            'view mahasiswa', // Semua mahasiswa
            'view prodi',
            'view jenjang pendidikan',
            'view krs', // Semua KRS
            'view krs detail', // Semua detail KRS
            'create krs', // Buatkan KRS untuk mahasiswa (opsional)
            'edit krs', // Edit KRS mahasiswa
            'approve krs', // Approve KRS mahasiswa
            'reject krs', // Reject KRS mahasiswa
            'view khs', // Semua KHS
            'view khs detail', // Semua detail KHS
            'view nilai', // Semua nilai (validasi)
            'view presensi', // Semua presensi (validasi)
            'edit nilai', // Edit nilai (jika diperlukan)
            'view status akademik mahasiswa', // Semua status
            'create status akademik mahasiswa', // Ubah status (Cuti, DO, Lulus)
            'edit status akademik mahasiswa', // Ubah status
            'view perwalian', // Semua perwalian
            'create perwalian', // Buatkan catatan perwalian
            'edit perwalian', // Edit catatan perwalian
            'approve perwalian', // Approve perwalian
            'reject perwalian', // Reject perwalian
            'view dosen', // Info dosen (wali)
            'view kelas mk', // Kelas MK (untuk KRS/KHS)
            'view mata kuliah',
            'view alumni', // Data kelulusan
            'create alumni', // Validasi kelulusan
            'edit alumni', // Edit data alumni
            'view kelas mahasiswa',
            'create kelas mahasiswa', // Assign mahasiswa ke kelas pararel
            'edit kelas mahasiswa', // Pindahkan mahasiswa ke kelas pararel
        ]);

        // --- Create Admin User ---
        // Ganti nilai-nilai ini sesuai kebutuhan Anda
        $adminUser = User::create([
            'name' => 'Admin Super',
            'email' => 'adminsuper@example.com',
            'password' => Hash::make('password123'), // Ganti dengan password yang aman
            'status' => 'aktif', // Sesuaikan dengan enum atau string yang digunakan
        ]);

        // Assign role admin ke user yang baru dibuat
        $adminUser->assignRole($admin->name);

        $this->command->info('Admin user created successfully.');
    }
}
