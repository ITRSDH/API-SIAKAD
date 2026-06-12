<?php

namespace App\Services;

class StudentAngkatanResolverService
{
    public function resolve(?string $angkatanInput, ?string $nim): ?int
    {
        $manualAngkatan = $this->normalizeManualAngkatan($angkatanInput);
        if ($manualAngkatan !== null) {
            return $manualAngkatan;
        }

        return $this->extractFromNim($nim);
    }

    public function extractFromNim(?string $nim): ?int
    {
        $normalizedNim = $this->normalizeNim($nim);
        if ($normalizedNim === null || strlen($normalizedNim) < 4) {
            return null;
        }

        $kodeAngkatan = substr($normalizedNim, 2, 2);
        if (!ctype_digit($kodeAngkatan)) {
            return null;
        }

        $angkatan = 2000 + (int) $kodeAngkatan;

        return $this->isValidAngkatan($angkatan) ? $angkatan : null;
    }

    public function isValidAngkatan(?int $angkatan): bool
    {
        if ($angkatan === null) {
            return false;
        }

        $minYear = 2000;
        $maxYear = (int) date('Y') + 1;

        return $angkatan >= $minYear && $angkatan <= $maxYear;
    }

    private function normalizeManualAngkatan(?string $angkatanInput): ?int
    {
        if (!filled($angkatanInput) || !is_numeric($angkatanInput)) {
            return null;
        }

        $angkatan = (int) $angkatanInput;

        return $this->isValidAngkatan($angkatan) ? $angkatan : null;
    }

    private function normalizeNim(?string $nim): ?string
    {
        if (!filled($nim)) {
            return null;
        }

        $normalized = preg_replace('/\s+/', '', trim((string) $nim));
        if (!is_string($normalized) || $normalized === '') {
            return null;
        }

        if (ctype_digit($normalized) && strlen($normalized) < 7) {
            return str_pad($normalized, 7, '0', STR_PAD_LEFT);
        }

        return $normalized;
    }
}
