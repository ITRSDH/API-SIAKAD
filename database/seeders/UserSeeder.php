<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\Mahasiswa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // --- Admin User ---
        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'status' => 'aktif',
        ]);
        $adminUser->assignRole('admin');

        // --- BAK User ---
        $bakUser = User::create([
            'name' => 'BAK User',
            'email' => 'bak@example.com',
            'password' => Hash::make('password'),
            'status' => 'aktif',
        ]);
        $bakUser->assignRole('baak');

        // --- Ambil ID Prodi ---
        $prodiId = \App\Models\MasterData\Prodi::first()->id;

        // Ambil kelas pararel (pastikan sudah ada data kelas pararel)
        $kelasPararel = \App\Models\MasterData\KelasPararel::first()->id;


        // --- Buat User Kaprodi terlebih dahulu ---
        $kaprodiUser = User::create([
            'name' => 'Kaprodi User',
            'email' => 'kaprodi@example.com',
            'password' => Hash::make('password'),
            'status' => 'aktif',
        ]);
        $kaprodiUser->assignRole('kaprodi');

        // --- Buat Dosen Kaprodi dengan user_id ---
        $kaprodiDosen = Dosen::create([
            'user_id' => $kaprodiUser->id, // Hubungkan user_id saat insert
            'id_prodi' => $prodiId,
            'nidn' => 'NIDN001',
            'nup' => 'NUP001',
            'nama_dosen' => 'Kaprodi Keperawatan',
            'jenis_kelamin' => 'P',
            'tanggal_lahir' => '1980-01-01',
            'alamat' => 'Alamat Kaprodi',
            'no_hp' => '081234567890',
            // 'email' => 'kaprodi@example.com',
            // 'jabatan_akademik' => 'Lektor',
            // 'pangkat_golongan' => 'III/d',
            // 'status_aktif' => true,
        ]);

        // --- Buat User Dosen Pengampu terlebih dahulu ---
        $dosenPengampuUser = User::create([
            'name' => 'Dosen Pengampu User',
            'email' => 'dosen.pengampu@example.com',
            'password' => Hash::make('password'),
            'status' => 'aktif',
        ]);
        $dosenPengampuUser->assignRole('dosen_pengampu');

        // --- Buat Dosen Biasa dengan user_id ---
        $dosenBiasa = Dosen::create([
            'user_id' => $dosenPengampuUser->id, // Hubungkan user_id saat insert
            'id_prodi' => $prodiId,
            'nidn' => 'NIDN002',
            'nup' => 'NUP002',
            'nama_dosen' => 'Dosen Biasa',
            'jenis_kelamin' => 'L',
            'tanggal_lahir' => '1985-05-10',
            'alamat' => 'Alamat Dosen',
            'no_hp' => '081234567891',
            // 'email' => 'dosen@example.com', // Harus berbeda dari kaprodi
            // 'jabatan_akademik' => 'Asisten Ahli',
            // 'pangkat_golongan' => 'III/c',
            // 'status_aktif' => true,
        ]);

        // --- Buat User Dosen PA terlebih dahulu ---
        $dosenPaUser = User::create([
            'name' => 'Dosen PA User',
            'email' => 'dosen.pa@example.com',
            'password' => Hash::make('password'),
            'status' => 'aktif',
        ]);
        $dosenPaUser->assignRole('dosen_pa');

        // --- Buat Dosen PA dengan user_id ---
        $dosenPA = Dosen::create([
            'user_id' => $dosenPaUser->id, // Hubungkan user_id saat insert
            'id_prodi' => $prodiId,
            'nidn' => 'NIDN003',
            'nup' => 'NUP003',
            'nama_dosen' => 'Dosen PA',
            'jenis_kelamin' => 'P',
            'tanggal_lahir' => '1982-07-15',
            'alamat' => 'Alamat Dosen PA',
            'no_hp' => '081234567892',
            // 'email' => 'dosen.pa@example.com',
            // 'jabatan_akademik' => 'Lektor',
            // 'pangkat_golongan' => 'III/d',
            // 'status_aktif' => true,
        ]);

        // --- Ambil ID Dosen Wali untuk Mahasiswa ---
        $dosenWaliId = $dosenPA->id;

        // --- Buat User Mahasiswa terlebih dahulu ---
        $mahasiswaUser = User::create([
            'name' => 'Mahasiswa User',
            'email' => 'mahasiswa@example.com',
            'password' => Hash::make('password'),
            'status' => 'aktif',
        ]);
        $mahasiswaUser->assignRole('mahasiswa');

        // --- Buat Mahasiswa dengan user_id ---
        $mahasiswa = Mahasiswa::create([
            'user_id' => $mahasiswaUser->id, // Hubungkan user_id saat insert
            'id_prodi' => $prodiId,
            'id_kelas_pararel' => $kelasPararel,
            'id_dosen' => $dosenWaliId,
            'nim' => '2023001',
            'nama_mahasiswa' => 'Mahasiswa Contoh',
            'jenis_kelamin' => 'P',
            'tanggal_lahir' => '2005-03-20',
            'alamat' => 'Alamat Mahasiswa',
            'no_hp' => '081234567893',
            // 'email' => 'mahasiswa@example.com',
            'asal_sekolah' => 'SMA Contoh',
            'nama_orang_tua' => 'Orang Tua Mahasiswa',
            'no_hp_orang_tua' => '081234567894',
            'status' => 'Aktif',
            'angkatan' => 2023,
        ]);

        // Catatan: Urutan sekarang: User dibuat dulu, role diassign, lalu Dosen/Mahasiswa dibuat dengan user_id.
    }
}
