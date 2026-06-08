<?php

namespace App\Imports;

use App\Services\Khs\KhsExcelParserService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class KhsNilaiImport implements ToCollection, WithChunkReading
{
    private array $parsedPayload = [];

    public function __construct(
        private readonly ?KhsExcelParserService $parserService = null
    ) {
    }

    public function collection(Collection $rows)
    {
        $parserService = $this->parserService ?? app(KhsExcelParserService::class);
        $this->parsedPayload = $parserService->parse($rows);
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function getParsedPayload(): array
    {
        return $this->parsedPayload;
    }
}
