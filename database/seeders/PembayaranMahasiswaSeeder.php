<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PembayaranMahasiswaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mahasiswa = DB::table('mahasiswa')->where('nim', '241001')->value('id');
        $jenisPembayaran = DB::table('jenis_pembayaran')->where('nama_pembayaran', 'Uang Semester')->value('id');

        $pembayarans = [
            [
                'id' => (string) Str::uuid(),
                'id_mahasiswa' => $mahasiswa,
                'id_jenis_pembayaran' => $jenisPembayaran,
                'tanggal_bayar' => now()->subDays(5)->toDateString(), // 5 hari lalu
                'jumlah_bayar' => 5000000,
                'status_pembayaran' => 'Lunas',
                'keterangan' => 'Pembayaran semester ganjil 2024/2025.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('pembayaran_mahasiswa')->insert($pembayarans);
    }
}
