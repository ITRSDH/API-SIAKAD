<?php

namespace App\Services\Khs;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class KhsExcelParserService
{
    public function parse(Collection $rows): array
    {
        return [
            'metadata' => [],
            'subjects' => [],
            'rows' => $rows->toArray(),
        ];
    }

    public function parseFile(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, true, false);

        if (count($rows) < 4) {
            throw new RuntimeException('Format file tidak valid. Minimal harus memiliki 4 baris.');
        }

        $headerRowIndex = $this->detectHeaderRowIndex($rows);
        $metadataRow = $this->detectMetadataRow($rows, $headerRowIndex);
        $groupRow = $headerRowIndex > 0 ? ($rows[$headerRowIndex - 1] ?? []) : [];
        $headerRow = $rows[$headerRowIndex] ?? [];
        $thirdRow = $rows[$headerRowIndex + 1] ?? [];
        $fourthRow = $rows[$headerRowIndex + 2] ?? [];

        $metadata = [
            'raw' => trim((string) ($metadataRow[0] ?? '')),
        ];
        $metadata = array_merge($metadata, $this->extractMetadata($metadata['raw']));

        $baseHeaders = [
            strtoupper(trim((string) ($headerRow[0] ?? 'NO'))),
            strtoupper(trim((string) ($headerRow[1] ?? ''))),
            strtoupper(trim((string) ($headerRow[2] ?? ''))),
        ];

        if ($baseHeaders !== ['NO', 'NIM', 'NAMA']) {
            throw new RuntimeException('Format header tidak sesuai. Kolom awal harus No, NIM, NAMA.');
        }

        $lastHeaderIndex = count($headerRow) - 1;
        if ($lastHeaderIndex < 5) {
            throw new RuntimeException('Format header mata kuliah tidak valid.');
        }

        $tailConfig = $this->resolveTailConfig($headerRow, [$groupRow, $metadataRow]);
        $tailHeaders = $tailConfig['headers'];
        $tailColumnCount = $tailConfig['count'];
        $metadata['tail_mode'] = $tailColumnCount === 4 ? 'separated' : 'combined';

        if ($tailColumnCount === 0) {
            throw new RuntimeException('Format kolom akhir tidak sesuai. Gunakan Jumlah, IP/IPK, Keterangan atau Jumlah, IP, IPK, Keterangan.');
        }

        if (
            strtoupper(trim((string) ($thirdRow[1] ?? ''))) === 'S K S' ||
            strtoupper(trim((string) ($thirdRow[2] ?? ''))) === 'S K S'
        ) {
            return $this->parseGroupedTemplate($rows, $metadata, $headerRow, $groupRow, $thirdRow, $headerRowIndex + 2);
        }

        $subjectEndIndex = count($headerRow) - $tailColumnCount;
        $isLegacyFormat = strtoupper(trim((string) ($thirdRow[3] ?? ''))) === 'SKS';
        $blockSize = $isLegacyFormat ? 5 : 4;
        $subHeaderRow = $isLegacyFormat ? $thirdRow : $fourthRow;
        $sksRow = $isLegacyFormat ? [] : $thirdRow;
        $dataRows = array_slice($rows, $headerRowIndex + ($isLegacyFormat ? 2 : 3));
        $subjects = [];

        for ($column = 3; $column < $subjectEndIndex; $column += $blockSize) {
            $subjectHeader = $this->resolveSubjectHeader($headerRow, $column, $subjectEndIndex);
            if ($subjectHeader === '') {
                throw new RuntimeException('Header mata kuliah kosong pada salah satu blok kolom.');
            }

            if ($isLegacyFormat) {
                $subHeaders = array_map(
                    fn($value) => strtoupper(trim((string) $value)),
                    array_slice($subHeaderRow, $column, 5)
                );

                if ($subHeaders !== ['SKS', 'NA', 'NH', 'BOBOT', 'MUTU']) {
                    throw new RuntimeException('Struktur blok MK tidak sesuai SKS | NA | NH | Bobot | Mutu.');
                }
            } else {
                $subHeaders = array_map(
                    fn($value) => strtoupper(trim((string) $value)),
                    array_slice($subHeaderRow, $column, 4)
                );

                if ($subHeaders !== ['NA', 'NH', 'BOBOT', 'MUTU']) {
                    throw new RuntimeException('Struktur blok MK tidak sesuai NA | NH | Bobot | Mutu pada format template baru.');
                }
            }

            [$namaMk, $kodeMk] = $this->extractSubjectIdentity($subjectHeader);
            $subjects[] = [
                'start_index' => $column,
                'nama_mk' => $namaMk,
                'kode_mk' => $kodeMk,
                'sks' => $isLegacyFormat ? null : $this->normalizeNumeric($sksRow[$column] ?? null, true),
            ];
        }

        $parsedRows = [];
        foreach ($dataRows as $excelRowNumber => $row) {
            $nim = trim((string) ($row[1] ?? ''));
            $nama = trim((string) ($row[2] ?? ''));

            if ($nim === '' && $nama === '') {
                continue;
            }

            $subjectRows = [];
            foreach ($subjects as $subject) {
                $offset = $subject['start_index'];
                $subjectRows[] = [
                    'kode_mk' => $subject['kode_mk'],
                    'nama_mk' => $subject['nama_mk'],
                    'sks' => $isLegacyFormat
                        ? $this->normalizeNumeric($row[$offset] ?? null, true)
                        : $subject['sks'],
                    'nilai_akhir' => $this->normalizeNumeric($row[$offset + ($isLegacyFormat ? 1 : 0)] ?? null),
                    'nilai_huruf' => $this->normalizeString($row[$offset + ($isLegacyFormat ? 2 : 1)] ?? null),
                    'bobot_nilai' => $this->normalizeNumeric($row[$offset + ($isLegacyFormat ? 3 : 2)] ?? null),
                    'mutu' => $this->normalizeNumeric($row[$offset + ($isLegacyFormat ? 4 : 3)] ?? null),
                ];
            }

            $parsedRows[] = [
                'row_number' => $excelRowNumber + $headerRowIndex + ($isLegacyFormat ? 3 : 4),
                'no' => $this->normalizeNumeric($row[0] ?? null, true),
                'nim' => $nim,
                'nama' => $nama,
                'jumlah' => $this->normalizeNumeric($row[$subjectEndIndex] ?? null),
                'ips_excel' => $this->normalizeNumeric($row[$subjectEndIndex + 1] ?? null),
                'ipk_excel' => $tailColumnCount === 4
                    ? $this->normalizeNumeric($row[$subjectEndIndex + 2] ?? null)
                    : $this->normalizeNumeric($row[$subjectEndIndex + 1] ?? null),
                'keterangan' => $this->normalizeString($row[$subjectEndIndex + ($tailColumnCount === 4 ? 3 : 2)] ?? null),
                'subjects' => $subjectRows,
            ];
        }

        return [
            'metadata' => $metadata,
            'subjects' => $subjects,
            'rows' => $parsedRows,
        ];
    }

    private function parseGroupedTemplate(array $rows, array $metadata, array $headerRow, array $groupRow, array $sksRow, int $dataStartIndex): array
    {
        $tailColumnCount = $this->resolveTailColumnCountFromMetadata($metadata, $headerRow, $groupRow);
        $subjectAreaCount = count($headerRow) - (3 + $tailColumnCount);
        if ($tailColumnCount === 0 || $subjectAreaCount <= 0 || ($subjectAreaCount % 4) !== 0) {
            throw new RuntimeException('Format grouped template KHS tidak valid.');
        }

        $subjectCount = (int) ($subjectAreaCount / 4);
        $scoreStart = 3;
        $nilaiGroupWidth = $subjectCount * 2;
        $mutuStart = $scoreStart + $nilaiGroupWidth;
        $bobotStart = $mutuStart + $subjectCount;
        $tailStart = $bobotStart + $subjectCount;

        $subjects = [];
        for ($index = 0; $index < $subjectCount; $index++) {
            $subjectHeader = trim((string) ($headerRow[$scoreStart + ($index * 2)] ?? ''));
            if ($subjectHeader === '') {
                $subjectHeader = trim((string) ($headerRow[$scoreStart + ($index * 2) + 1] ?? ''));
            }
            if ($subjectHeader === '') {
                throw new RuntimeException('Header mata kuliah kosong pada grouped template.');
            }

            [$namaMk, $kodeMk] = $this->extractSubjectIdentity($subjectHeader);
            $subjects[] = [
                'start_index' => $scoreStart + ($index * 2),
                'nama_mk' => $namaMk,
                'kode_mk' => $kodeMk,
                'sks' => $this->normalizeNumeric($sksRow[$scoreStart + ($index * 2) + 1] ?? null, true),
            ];
        }

        $parsedRows = [];
        $dataRows = array_slice($rows, $dataStartIndex);
        foreach ($dataRows as $excelRowNumber => $row) {
            $nim = trim((string) ($row[1] ?? ''));
            $nama = trim((string) ($row[2] ?? ''));

            if ($nim === '' && $nama === '') {
                continue;
            }

            $subjectRows = [];
            foreach ($subjects as $index => $subject) {
                $subjectRows[] = [
                    'kode_mk' => $subject['kode_mk'],
                    'nama_mk' => $subject['nama_mk'],
                    'sks' => $subject['sks'],
                    'nilai_akhir' => $this->normalizeNumeric($row[$scoreStart + ($index * 2)] ?? null),
                    'nilai_huruf' => $this->normalizeString($row[$scoreStart + ($index * 2) + 1] ?? null),
                    'mutu' => $this->normalizeNumeric($row[$mutuStart + $index] ?? null),
                    'bobot_nilai' => $this->normalizeNumeric($row[$bobotStart + $index] ?? null),
                ];
            }

            $parsedRows[] = [
                'row_number' => $excelRowNumber + $dataStartIndex + 1,
                'no' => $this->normalizeNumeric($row[0] ?? null, true),
                'nim' => $nim,
                'nama' => $nama,
                'jumlah' => $this->normalizeNumeric($row[$tailStart] ?? null),
                'ips_excel' => $this->normalizeNumeric($row[$tailStart + 1] ?? null),
                'ipk_excel' => $tailColumnCount === 4
                    ? $this->normalizeNumeric($row[$tailStart + 2] ?? null)
                    : $this->normalizeNumeric($row[$tailStart + 1] ?? null),
                'keterangan' => $this->normalizeString($row[$tailStart + ($tailColumnCount === 4 ? 3 : 2)] ?? null),
                'subjects' => $subjectRows,
            ];
        }

        return [
            'metadata' => $metadata,
            'subjects' => $subjects,
            'rows' => $parsedRows,
        ];
    }

    private function resolveSubjectHeader(array $headerRow, int $column, int $subjectEndIndex): string
    {
        $header = trim((string) ($headerRow[$column] ?? ''));
        if ($header !== '') {
            return $header;
        }

        for ($index = $column - 1; $index >= 3; $index--) {
            $candidate = trim((string) ($headerRow[$index] ?? ''));
            if ($candidate !== '' && ($column - $index) < 5) {
                return $candidate;
            }
        }

        return '';
    }

    private function detectHeaderRowIndex(array $rows): int
    {
        $maxIndex = min(count($rows) - 1, 5);

        for ($index = 0; $index <= $maxIndex; $index++) {
            $row = $rows[$index] ?? [];
            $col0 = strtoupper(trim((string) ($row[0] ?? '')));
            $col1 = strtoupper(trim((string) ($row[1] ?? '')));
            $col2 = strtoupper(trim((string) ($row[2] ?? '')));

            if (($col0 === 'NO' || $col0 === '') && $col1 === 'NIM' && $col2 === 'NAMA') {
                return $index;
            }
        }

        return 1;
    }

    private function detectMetadataRow(array $rows, int $headerRowIndex): array
    {
        for ($index = 0; $index < $headerRowIndex; $index++) {
            $row = $rows[$index] ?? [];
            $firstCell = strtoupper(trim((string) ($row[0] ?? '')));

            if (str_contains($firstCell, 'FORMAT_IMPORT_KHS')) {
                return $row;
            }
        }

        return $headerRowIndex > 0 ? ($rows[$headerRowIndex - 1] ?? []) : [];
    }

    private function resolveTailConfig(array $headerRow, array $fallbackRows = []): array
    {
        foreach ([4, 3] as $tailCount) {
            $tailHeaders = array_map(
                fn($value) => strtoupper(trim((string) $value)),
                array_slice($headerRow, -$tailCount)
            );

            if ($this->isValidTailHeaders($tailHeaders)) {
                return [
                    'headers' => $tailHeaders,
                    'count' => $tailCount,
                ];
            }
        }

        foreach ($fallbackRows as $fallbackRow) {
            foreach ([4, 3] as $tailCount) {
                $tailHeaders = array_map(
                    fn($value) => strtoupper(trim((string) $value)),
                    array_slice((array) $fallbackRow, -$tailCount)
                );

                if ($this->isValidTailHeaders($tailHeaders)) {
                    return [
                        'headers' => $tailHeaders,
                        'count' => $tailCount,
                    ];
                }
            }
        }

        return [
            'headers' => [],
            'count' => 0,
        ];
    }

    private function isValidTailHeaders(array $tailHeaders): bool
    {
        if (count($tailHeaders) === 3) {
            return in_array($tailHeaders[0] ?? '', ['JUMLAH', 'JML'], true)
                && ($tailHeaders[1] ?? '') === 'IP/IPK'
                && in_array($tailHeaders[2] ?? '', ['KETERANGAN', 'KETERANGAN '], true);
        }

        if (count($tailHeaders) === 4) {
            return in_array($tailHeaders[0] ?? '', ['JUMLAH', 'JML'], true)
                && ($tailHeaders[1] ?? '') === 'IP'
                && ($tailHeaders[2] ?? '') === 'IPK'
                && in_array($tailHeaders[3] ?? '', ['KETERANGAN', 'KETERANGAN '], true);
        }

        return false;
    }

    private function extractMetadata(string $metadataRaw): array
    {
        $metadata = [];

        if (preg_match('/SEMESTER_KE\s*=\s*(\d+)/i', $metadataRaw, $matches)) {
            $metadata['semester_ke'] = (int) $matches[1];
        }

        return $metadata;
    }

    private function resolveTailColumnCountFromMetadata(array $metadata, array $headerRow, array $groupRow = []): int
    {
        if (((int) ($metadata['semester_ke'] ?? 0)) > 1) {
            return 4;
        }

        if (((int) ($metadata['semester_ke'] ?? 0)) === 1) {
            return 3;
        }

        $tailConfig = $this->resolveTailConfig($headerRow, [$groupRow]);

        return $tailConfig['count'];
    }

    private function extractSubjectIdentity(string $subjectHeader): array
    {
        // if (preg_match('/^(.*)\s+([A-Za-z0-9]+)$/', trim($subjectHeader), $matches)) {
        //     return [
        //         trim($matches[1]),
        //         trim($matches[2]),
        //     ];
        // }

        // return [trim($subjectHeader), trim($subjectHeader)];
        if (preg_match('/^(.*?)\s+([A-Za-z0-9]+(?:[.][A-Za-z0-9]+)*)$/', $subjectHeader, $matches)) {
            return [
                trim($matches[1]),
                trim($matches[2]),
            ];
        }

        return [
            $subjectHeader,
            $subjectHeader,
        ];
    }

    private function normalizeNumeric(mixed $value, bool $integer = false): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $normalized = trim($value);
            $normalized = str_replace(' ', '', $normalized);

            if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
                $lastComma = strrpos($normalized, ',');
                $lastDot = strrpos($normalized, '.');

                if ($lastComma !== false && $lastDot !== false) {
                    if ($lastComma > $lastDot) {
                        $normalized = str_replace('.', '', $normalized);
                        $normalized = str_replace(',', '.', $normalized);
                    } else {
                        $normalized = str_replace(',', '', $normalized);
                    }
                }
            } elseif (str_contains($normalized, ',')) {
                $normalized = str_replace(',', '.', $normalized);
            }

            $value = $normalized;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return $integer ? (int) round((float) $value) : round((float) $value, 2);
    }

    private function normalizeString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
