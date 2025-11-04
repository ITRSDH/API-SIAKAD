<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JenisPembayaranSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jenisPembayarans = [
            [
                'id' => (string) Str::uuid(),
                'nama_pembayaran' => 'Uang Semester',
                'nominal' => 5000000, // 5 Juta Rupiah
                'keterangan' => 'Pembayaran untuk semester aktif.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'nama_pembayaran' => 'Uang Praktikum',
                'nominal' => 1000000, // 1 Juta Rupiah
                'keterangan' => 'Pembayaran fasilitas praktikum.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'nama_pembayaran' => 'Uang Wisuda',
                'nominal' => 2000000, // 2 Juta Rupiah
                'keterangan' => 'Pembayaran wisuda.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('jenis_pembayaran')->insert($jenisPembayarans);
    }
}
