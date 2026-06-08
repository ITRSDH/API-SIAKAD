<?php

namespace App\Services\Khs;

class GradeConversionService
{
    public function convertNumericScore(float $nilaiAkhir): array
    {
        return match (true) {
            $nilaiAkhir >= 80 => ['nilai_huruf' => 'A', 'bobot_nilai' => 4.00],
            $nilaiAkhir >= 75 => ['nilai_huruf' => 'B+', 'bobot_nilai' => 3.50],
            $nilaiAkhir >= 70 => ['nilai_huruf' => 'B', 'bobot_nilai' => 3.00],
            $nilaiAkhir >= 65 => ['nilai_huruf' => 'C+', 'bobot_nilai' => 2.50],
            $nilaiAkhir >= 60 => ['nilai_huruf' => 'C', 'bobot_nilai' => 2.00],
            $nilaiAkhir >= 50 => ['nilai_huruf' => 'D', 'bobot_nilai' => 1.00],
            default => ['nilai_huruf' => 'E', 'bobot_nilai' => 0.00],
        };
    }

    public function convertLetterGrade(string $nilaiHuruf): ?array
    {
        $normalized = strtoupper(trim($nilaiHuruf));

        return match ($normalized) {
            'A' => ['nilai_huruf' => 'A', 'bobot_nilai' => 4.00],
            'A-' => ['nilai_huruf' => 'A-', 'bobot_nilai' => 3.75],
            'B+' => ['nilai_huruf' => 'B+', 'bobot_nilai' => 3.50],
            'B' => ['nilai_huruf' => 'B', 'bobot_nilai' => 3.00],
            'B-' => ['nilai_huruf' => 'B-', 'bobot_nilai' => 2.75],
            'C+' => ['nilai_huruf' => 'C+', 'bobot_nilai' => 2.50],
            'C' => ['nilai_huruf' => 'C', 'bobot_nilai' => 2.00],
            'D' => ['nilai_huruf' => 'D', 'bobot_nilai' => 1.00],
            'E' => ['nilai_huruf' => 'E', 'bobot_nilai' => 0.00],
            default => null,
        };
    }
}
