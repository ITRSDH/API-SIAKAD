<?php

namespace App\Services\Khs;

class KhsRemarkService
{
    public function resolveFromIps(float $ips): string
    {
        return match (true) {
            $ips >= 3.50 => 'Terpuji, Pertahankan Prestasi Anda',
            $ips >= 3.00 => 'Sangat Memuaskan, Harap Pertahankan Prestasi Anda',
            $ips >= 2.76 => 'Memuaskan, Pertahankan dan Tingkatkan Prestasi Anda',
            $ips >= 2.00 => 'Cukup, Harap Ditingkatkan Prestasi Anda dan Belajar Yang Lebih Giat',
            default => 'Tidak Lulus',
        };
    }

    public function matchesExcelRemark(float $ips, ?string $excelRemark): bool
    {
        if ($excelRemark === null || trim($excelRemark) === '') {
            return false;
        }

        return trim($excelRemark) === $this->resolveFromIps($ips);
    }
}
