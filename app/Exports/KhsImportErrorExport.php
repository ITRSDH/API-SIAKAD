<?php

namespace App\Exports;

use App\Models\Akademik\KhsImportBatch;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class KhsImportErrorExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly KhsImportBatch $batch
    ) {
    }

    public function collection()
    {
        return $this->batch->errors()->orderBy('row_number')->orderBy('created_at')->get();
    }

    public function headings(): array
    {
        return [
            'ROW_NUMBER',
            'NIM',
            'KODE_MK',
            'ERROR_TYPE',
            'MESSAGE',
        ];
    }

    public function map($error): array
    {
        return [
            $error->row_number,
            $error->nim,
            $error->kode_mk,
            $error->error_type,
            $error->message,
        ];
    }
}
