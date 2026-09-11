<?php

namespace App\Services;

use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\Prodi;
use InvalidArgumentException;

class StudentNimGeneratorService
{
    /**
     * Generate an official student identification number (NIM).
     *
     * Format:
     * - REGULER : [2-digit Kode Prodi][2-digit Angkatan][3-digit Nomor Urut] (Contoh: 0126001)
     * - RPL     : [2-digit Kode Prodi][2-digit Angkatan][3-digit Nomor Urut]B (Contoh: 0126001B)
     *
     * @param  string  $idProdi  UUID of the study program
     * @param  int|string  $angkatan  4-digit or 2-digit matriculation year (e.g. 2026 or 26)
     * @param  string|null  $jalur  Admission path ('Reguler', 'RPL', 'Pindahan', etc.)
     * @return string Generated NIM
     *
     * @throws InvalidArgumentException
     */
    public function generate(string $idProdi, int|string $angkatan, ?string $jalur = 'Reguler'): string
    {
        $prodi = Prodi::find($idProdi);
        if (! $prodi) {
            throw new InvalidArgumentException("Program studi dengan ID [{$idProdi}] tidak ditemukan.");
        }

        // 1. Dapatkan 2-digit kode prodi (e.g. '01', '02', '03', '04')
        $rawKodeProdi = trim((string) $prodi->kode_prodi);
        $kodeProdi = str_pad($rawKodeProdi, 2, '0', STR_PAD_LEFT);
        if (strlen($kodeProdi) > 2) {
            $kodeProdi = substr($kodeProdi, 0, 2);
        }

        // 2. Dapatkan 2-digit tahun angkatan (e.g. 2026 -> '26')
        $cleanAngkatan = trim((string) $angkatan);
        if (strlen($cleanAngkatan) >= 4) {
            $kodeTahun = substr($cleanAngkatan, -2);
        } else {
            $kodeTahun = str_pad($cleanAngkatan, 2, '0', STR_PAD_LEFT);
        }

        $prefix = $kodeProdi.$kodeTahun;

        // 3. Tentukan apakah mahasiswa berjalur RPL / Alih Jenjang
        $isRpl = $this->isRplJalur($jalur);
        $suffix = $isRpl ? 'B' : '';

        // 4. Hitung nomor urut berikutnya
        $nextSeq = $this->resolveNextSequence($idProdi, $prefix, $isRpl);

        // 5. Rakit NIM dan pastikan unik
        do {
            $urutPadded = str_pad((string) $nextSeq, 3, '0', STR_PAD_LEFT);
            $candidateNim = $prefix.$urutPadded.$suffix;
            $exists = Mahasiswa::where('nim', $candidateNim)->exists();
            if ($exists) {
                $nextSeq++;
            }
        } while ($exists);

        return $candidateNim;
    }

    /**
     * Memeriksa apakah jalur masuk diklasifikasikan sebagai RPL / Alih Jenjang.
     */
    public function isRplJalur(?string $jalur): bool
    {
        if (! filled($jalur)) {
            return false;
        }

        $normalized = strtoupper(trim((string) $jalur));

        return in_array($normalized, [
            'RPL',
            'PINDAHAN',
            'ALIH JENJANG',
            'TRANSFER',
            'REKOGNISI PEMBELAJARAN LAMPAU',
        ], true);
    }

    /**
     * Mencari nomor urut tertinggi untuk prodi, angkatan, dan tipe jalur tersebut.
     */
    private function resolveNextSequence(string $idProdi, string $prefix, bool $isRpl): int
    {
        // Query seluruh NIM yang memiliki prefix yang sama (sudah mengandung kode prodi dan tahun)
        $query = Mahasiswa::where('nim', 'LIKE', $prefix.'%');

        if ($isRpl) {
            // RPL berakhiran 'B'
            $nims = $query->where('nim', 'LIKE', '%B')->pluck('nim');
            $pattern = '/^'.preg_quote($prefix, '/').'(\d{3,})B$/i';
        } else {
            // Reguler murni numerik tanpa 'B'
            $nims = $query->where('nim', 'NOT LIKE', '%B')->pluck('nim');
            $pattern = '/^'.preg_quote($prefix, '/').'(\d{3,})$/';
        }

        $maxSeq = 0;

        foreach ($nims as $nim) {
            if (preg_match($pattern, trim($nim), $matches)) {
                $seqVal = (int) $matches[1];
                if ($seqVal > $maxSeq) {
                    $maxSeq = $seqVal;
                }
            }
        }

        return $maxSeq + 1;
    }
}
