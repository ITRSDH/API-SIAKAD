<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call([
            // role permission
            RolePermissionSeeder::class,
            // Data Master
            JenjangPendidikanSeeder::class,
            ProdiSeeder::class,
            TahunAkademikSeeder::class,
            JenisKelasSeeder::class,
            RuangSeeder::class,
            JenisPembayaranSeeder::class,

            // Logika Bisnis
            SemesterSeeder::class,
            KurikulumSeeder::class,
            // MataKuliahSeeder::class,
            KelasPararelSeeder::class,
            // DosenSeeder::class,
            // MahasiswaSeeder::class,
            // KelasMkSeeder::class,
            // JadwalKuliahSeeder::class,
            // DosenKelasMkSeeder::class,
            // BebanAjarDosenSeeder::class,
            // PresensiSeeder::class,
            // NilaiSeeder::class,
            // KrsSeeder::class,
            // KrsDetailSeeder::class,
            // KhsSeeder::class,
            // KhsDetailSeeder::class,
            // PembayaranMahasiswaSeeder::class,
            // StatusAkademikMahasiswaSeeder::class,
            // PerwalianSeeder::class,
            // BerkasMahasiswaSeeder::class,
            // AlumniSeeder::class,
            // KelasMahasiswaSeeder::class,

            // Auth
            UserSeeder::class,

            // Website
            ProfileKampusSeeder::class,
            LandingContentSeeder::class,
        ]);
    }
}
