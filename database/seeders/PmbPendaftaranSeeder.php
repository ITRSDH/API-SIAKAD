<?php

namespace Database\Seeders;

use App\Models\Website\PmbPendaftaran;
use Illuminate\Database\Seeder;

class PmbPendaftaranSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            'deskripsi' => 'Informasi lengkap mengenai Penerimaan Mahasiswa Baru (PMB), meliputi pengumuman, jalur masuk, syarat pendaftaran, dan biaya yang berlaku.',
            'tata_cara' => "1. **Pengumuman Penerimaan Mahasiswa Baru**\n   - Kampus mengumumkan jadwal PMB.\n   - Informasi jalur masuk (reguler, prestasi, beasiswa, dll).\n   - Syarat pendaftaran dan biaya.\n   - Media: website resmi, media sosial, brosur.\n\n2. **Pendaftaran**\n   - Calon mahasiswa membuat akun PMB (online / offline).\n   - Mengisi formulir pendaftaran.\n   - Memilih program studi dan jalur masuk.\n   - Mendapat nomor pendaftaran / kartu peserta.\n\n3. **Pembayaran Biaya Pendaftaran**\n   - Melakukan pembayaran sesuai instruksi.\n   - Melalui: Bank, Virtual Account, Loket Kampus.\n   - Bukti pembayaran diunggah atau diverifikasi.\n\n4. **Unggah / Penyerahan Berkas**\n   Biasanya meliputi:\n   - Ijazah / Surat Keterangan Lulus.\n   - Transkrip nilai / rapor.\n   - KTP / KK.\n   - Pas foto.\n   - Sertifikat pendukung (jika jalur prestasi).\n   - Surat kesehatan (untuk prodi tertentu).",
        ];

        $existing = PmbPendaftaran::first();

        if ($existing) {
            $existing->update($data);
            return;
        }

        PmbPendaftaran::create($data);
    }
}
