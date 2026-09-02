<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class KhsImportResultExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly array $preview
    ) {
    }

    public function collection()
    {
        return collect($this->preview['rows'] ?? []);
    }

    public function headings(): array
    {
        return [
            'ROW_NUMBER',
            'NIM',
            'NAMA',
            'STATUS',
            'TOTAL_ERROR',
            'TOTAL_WARNING',
            'SKIPPED_COUNT',
            'IPS_EXCEL',
            'IPS_SISTEM',
            'IPK_EXCEL',
            'IPK_FINAL',
            'KETERANGAN_EXCEL',
            'KETERANGAN_SISTEM',
            'ERROR_MESSAGES',
            'WARNING_MESSAGES',
        ];
    }

    public function map($row): array
    {
        return [
            $row['row_number'] ?? null,
            $row['nim'] ?? null,
            $row['nama'] ?? null,
            ($row['is_valid'] ?? false) ? 'VALID' : 'ERROR',
            count($row['errors'] ?? []),
            count($row['warnings'] ?? []),
            collect($row['subjects'] ?? [])->where('skipped', true)->count(),
            $row['ips_excel'] ?? null,
            $row['summary']['ips'] ?? null,
            $row['ipk_excel'] ?? null,
            $row['summary']['ipk'] ?? null,
            $row['keterangan'] ?? null,
            $row['summary']['keterangan'] ?? null,
            implode(' | ', $row['errors'] ?? []),
            implode(' | ', $row['warnings'] ?? []),
        ];
    }
}
