<?php

namespace App\Exports;

use App\Services\Khs\KhsTemplateExportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Events\AfterSheet;

class KhsTemplateExport implements FromArray, WithEvents
{
    private array $template;
    private bool $usesSeparatedIpkColumn;

    public function __construct(array $filters, ?KhsTemplateExportService $service = null)
    {
        $service ??= app(KhsTemplateExportService::class);
        $this->template = $service->build($filters);
        $this->usesSeparatedIpkColumn = ((int) ($this->template['metadata']['semester_ke'] ?? 0)) > 1;
    }

    public function array(): array
    {
        $subjects = $this->template['subjects'];
        $rows = $this->template['rows'];
        $totalSks = (int) array_sum(array_map(
            static fn(array $subject): int => (int) ($subject['sks'] ?? 0),
            $subjects
        ));
        $tailHeaders = $this->tailHeaders();

        $sheetRows = [];
        $sheetRows[] = [$this->buildMetadataLabel()];

        $groupRow = ['No', '', ''];
        foreach ($subjects as $subject) {
            $groupRow[] = '';
            $groupRow[] = 'Lambang';
        }
        $groupRow = array_merge(
            $groupRow,
            array_fill(0, count($subjects), 'Mutu'),
            array_fill(0, count($subjects), 'Bobot'),
            $tailHeaders['group']
        );

        $headerRow = ['No', 'NIM', 'NAMA'];
        foreach ($subjects as $subject) {
            $headerRow[] = '';
            $headerRow[] = $subject['nama_mk'] . "\n" . $subject['kode_mk'];
        }
        foreach ($subjects as $subject) {
            $headerRow[] = $subject['nama_mk'] . "\n" . $subject['kode_mk'];
        }
        foreach ($subjects as $subject) {
            $headerRow[] = $subject['nama_mk'] . "\n" . $subject['kode_mk'];
        }
        $headerRow = array_merge($headerRow, $tailHeaders['header']);

        $sksRow = ['', 'S K S', ''];
        foreach ($subjects as $subject) {
            $sksRow[] = '';
            $sksRow[] = $subject['sks'];
        }
        foreach ($subjects as $subject) {
            $sksRow[] = $subject['sks'];
        }
        foreach ($subjects as $subject) {
            $sksRow[] = $subject['sks'];
        }
        $sksTail = [number_format($totalSks, 2, '.', ''), number_format($totalSks, 2, '.', '')];
        if ($this->usesSeparatedIpkColumn) {
            $sksTail[] = '';
        }
        $sksTail[] = '';
        $sksRow = array_merge($sksRow, $sksTail);

        $sheetRows[] = $groupRow;
        $sheetRows[] = $headerRow;
        $sheetRows[] = $sksRow;

        foreach ($rows as $row) {
            $sheetRow = [
                $row['no'],
                $row['nim'],
                $row['nama'],
            ];

            foreach ($row['subjects'] as $subject) {
                $sheetRow[] = '';
                $sheetRow[] = '';
            }
            for ($index = 0; $index < count($row['subjects']) * 2; $index++) {
                $sheetRow[] = '';
            }

            $sheetRow = array_merge($sheetRow, array_fill(0, count($tailHeaders['header']), ''));
            $sheetRows[] = $sheetRow;
        }

        return $sheetRows;
    }

    public function registerEvents(): array
    {
        $totalSks = (int) array_sum(array_map(
            static fn(array $subject): int => (int) ($subject['sks'] ?? 0),
            $this->template['subjects'] ?? []
        ));

        return [
            AfterSheet::class => function (AfterSheet $event) use ($totalSks) {
                $sheet = $event->sheet->getDelegate();
                $highestColumn = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestRow();
                $metadataRow = 1;
                $groupRowIndex = 2;
                $headerRowIndex = 3;
                $sksRowIndex = 4;
                $dataStartRow = 5;
                $headerStart = 4;
                $subjectCount = count($this->template['subjects'] ?? []);
                $nilaiGroupWidth = $subjectCount * 2;
                $mutuStartIndex = $headerStart + $nilaiGroupWidth;
                $bobotStartIndex = $mutuStartIndex + $subjectCount;
                $tailStartIndex = $bobotStartIndex + $subjectCount;
                $tailColumnCount = $this->usesSeparatedIpkColumn ? 4 : 3;
                $keteranganColumn = Coordinate::stringFromColumnIndex($tailStartIndex + ($this->usesSeparatedIpkColumn ? 3 : 2));

                $sheet->mergeCells('A1:' . $highestColumn . '1');
                $sheet->mergeCells('A2:A4');
                $sheet->mergeCells('B4:C4');
                for ($offset = 0; $offset < $tailColumnCount; $offset++) {
                    $column = Coordinate::stringFromColumnIndex($tailStartIndex + $offset);
                    $sheet->mergeCells($column . '2:' . $column . '3');
                }

                $sheet->getRowDimension($metadataRow)->setRowHeight(22);
                $sheet->getRowDimension($groupRowIndex)->setRowHeight(20);
                $sheet->getRowDimension($headerRowIndex)->setRowHeight(96);
                $sheet->getRowDimension($sksRowIndex)->setRowHeight(18);

                $sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 10,
                        'name' => 'Arial',
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle('A2:' . $highestColumn . '4')->applyFromArray([
                    'font' => [
                        'bold' => false,
                        'size' => 8,
                        'name' => 'Arial',
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFFFFF'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                $sheet->getStyle('A2:C4')->getAlignment()->setTextRotation(0);
                $sheet->getStyle(
                    Coordinate::stringFromColumnIndex($tailStartIndex) . '2:' .
                    Coordinate::stringFromColumnIndex($tailStartIndex + $tailColumnCount - 1) . '4'
                )->getAlignment()->setTextRotation(0);

                $sheet->getStyle('A2:' . $highestColumn . '4')->applyFromArray([
                    'borders' => [
                        'outline' => [
                            'borderStyle' => Border::BORDER_MEDIUM,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                if ($subjectCount > 0) {
                    $subjectHeaderRanges = [
                        [$headerStart, $mutuStartIndex - 1],
                        [$mutuStartIndex, $bobotStartIndex - 1],
                        [$bobotStartIndex, $tailStartIndex - 1],
                    ];

                    foreach ($subjectHeaderRanges as [$startIndex, $endIndex]) {
                        $sheet->getStyle(
                            Coordinate::stringFromColumnIndex($startIndex) . '3:' .
                            Coordinate::stringFromColumnIndex($endIndex) . '3'
                        )->getAlignment()
                            ->setTextRotation(90)
                            ->setWrapText(true)
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                            ->setVertical(Alignment::VERTICAL_CENTER);

                        $sheet->getStyle(
                            Coordinate::stringFromColumnIndex($startIndex) . '2:' .
                            Coordinate::stringFromColumnIndex($endIndex) . '4'
                        )->applyFromArray([
                            'borders' => [
                                'left' => [
                                    'borderStyle' => Border::BORDER_MEDIUM,
                                    'color' => ['rgb' => '000000'],
                                ],
                                'right' => [
                                    'borderStyle' => Border::BORDER_MEDIUM,
                                    'color' => ['rgb' => '000000'],
                                ],
                            ],
                        ]);
                    }
                }

                if ($highestRow >= $dataStartRow) {
                    $sheet->getStyle('A5:' . $highestColumn . $highestRow)->applyFromArray([
                        'font' => [
                            'size' => 10,
                            'name' => 'Arial',
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => '000000'],
                            ],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);

                    for ($row = $dataStartRow; $row <= $highestRow; $row++) {
                        $sheet->getRowDimension($row)->setRowHeight(22);

                        // Penanda visual per sel: abu-abu = MK tidak diambil, amber = MK ulang.
                        $templateRow = $this->template['rows'][$row - $dataStartRow] ?? null;

                        for ($subjectIndex = 0; $subjectIndex < $subjectCount; $subjectIndex++) {
                            $scoreColumn = Coordinate::stringFromColumnIndex($headerStart + ($subjectIndex * 2));
                            $lambangColumn = Coordinate::stringFromColumnIndex($headerStart + ($subjectIndex * 2) + 1);
                            $mutuColumn = Coordinate::stringFromColumnIndex($mutuStartIndex + $subjectIndex);
                            $bobotColumn = Coordinate::stringFromColumnIndex($bobotStartIndex + $subjectIndex);

                            $templateCell = $templateRow['subjects'][$subjectIndex] ?? null;
                            $isNotTaken = is_array($templateCell) && ($templateCell['taken'] ?? true) === false;
                            $isRepeat = is_array($templateCell) && ($templateCell['taken'] ?? false) === true && ($templateCell['is_repeat'] ?? false) === true;

                            if ($isNotTaken || $isRepeat) {
                                $fillColor = $isNotTaken ? 'D9D9D9' : 'FFF3CD';
                                foreach ([$scoreColumn, $lambangColumn, $mutuColumn, $bobotColumn] as $fillColumn) {
                                    $sheet->getStyle($fillColumn . $row)
                                        ->getFill()
                                        ->setFillType(Fill::FILL_SOLID)
                                        ->getStartColor()
                                        ->setRGB($fillColor);
                                }
                            }

                            $sheet->setCellValue(
                                $lambangColumn . $row,
                                '=IF(' . $scoreColumn . $row . '<=40,"E",IF(' . $scoreColumn . $row . '<=55,"D",IF(' . $scoreColumn . $row . '<=64,"C",IF(' . $scoreColumn . $row . '<=68,"C+",IF(' . $scoreColumn . $row . '<=74,"B",IF(' . $scoreColumn . $row . '<=79,"B+",IF(' . $scoreColumn . $row . '<=100,"A","")))))))'
                            );
                            $sheet->setCellValue(
                                $mutuColumn . $row,
                                '=IF(' . $lambangColumn . $row . '="E",0,IF(' . $lambangColumn . $row . '="D",1,IF(' . $lambangColumn . $row . '="C",2,IF(' . $lambangColumn . $row . '="C+",2.5,IF(' . $lambangColumn . $row . '="B",3,IF(' . $lambangColumn . $row . '="B+",3.5,IF(' . $lambangColumn . $row . '="A",4,"")))))))'
                            );
                            $sheet->setCellValue(
                                $bobotColumn . $row,
                                '=IF(' . $mutuColumn . $row . '="","",' . $mutuColumn . $row . '*' . $lambangColumn . '$4)'
                            );
                        }

                        $jumlahColumn = Coordinate::stringFromColumnIndex($tailStartIndex);
                        $ipColumn = Coordinate::stringFromColumnIndex($tailStartIndex + 1);
                        $ipkColumn = $this->usesSeparatedIpkColumn
                            ? Coordinate::stringFromColumnIndex($tailStartIndex + 2)
                            : null;

                        $sheet->setCellValue(
                            $jumlahColumn . $row,
                            '=SUM(' . Coordinate::stringFromColumnIndex($bobotStartIndex) . $row . ':' . Coordinate::stringFromColumnIndex($tailStartIndex - 1) . $row . ')'
                        );
                        $sheet->setCellValue(
                            $ipColumn . $row,
                            '=IF(' . $jumlahColumn . $row . '="","",' . $jumlahColumn . $row . '/' . $totalSks . ')'
                        );
                        if ($ipkColumn !== null) {
                            $sheet->getStyle($ipkColumn . $row)->getFill()->setFillType(Fill::FILL_SOLID);
                            $sheet->getStyle($ipkColumn . $row)->getFill()->getStartColor()->setRGB('FFF2CC');
                        }
                        $sheet->setCellValue(
                            $keteranganColumn . $row,
                            '=IF(' . $ipColumn . $row . '>=3.5,"Terpuji, Pertahankan Prestasi Anda",IF(' . $ipColumn . $row . '>=3,"Sangat Memuaskan, Harap Pertahankan Prestasi Anda",IF(' . $ipColumn . $row . '>=2.76,"Memuaskan, Pertahankan dan Tingkatkan Prestasi Anda",IF(' . $ipColumn . $row . '>=2,"Cukup, Harap Ditingkatkan Prestasi Anda dan Belajar Yang Lebih Giat ","Tidak Lulus"))))'
                        );
                    }
                }

                $sheet->setCellValueExplicit(
                    Coordinate::stringFromColumnIndex($tailStartIndex) . '4',
                    number_format($totalSks, 2, '.', ''),
                    DataType::TYPE_STRING
                );
                $sheet->setCellValueExplicit(
                    Coordinate::stringFromColumnIndex($tailStartIndex + 1) . '4',
                    number_format($totalSks, 2, '.', ''),
                    DataType::TYPE_STRING
                );

                $sheet->freezePane('D5');

                $sheet->getColumnDimension('A')->setWidth(4);
                $sheet->getColumnDimension('B')->setWidth(11);
                $sheet->getColumnDimension('C')->setWidth(22);

                for ($subjectIndex = 0; $subjectIndex < $subjectCount; $subjectIndex++) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($headerStart + ($subjectIndex * 2)))->setWidth(5.5);
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($headerStart + ($subjectIndex * 2) + 1))->setWidth(2.5);
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($mutuStartIndex + $subjectIndex))->setWidth(5.5);
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($bobotStartIndex + $subjectIndex))->setWidth(5);
                }

                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($tailStartIndex))->setWidth(8);
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($tailStartIndex + 1))->setWidth(8);
                if ($this->usesSeparatedIpkColumn) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($tailStartIndex + 2))->setWidth(8);
                }
                $sheet->getColumnDimension($keteranganColumn)->setWidth(24);

                $sheet->getStyle('C5:C' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle(
                    $keteranganColumn . '5:' . $keteranganColumn . $highestRow
                )->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $sheet->getStyle(
                    Coordinate::stringFromColumnIndex($headerStart) . '5:' .
                    Coordinate::stringFromColumnIndex($mutuStartIndex - 1) . $highestRow
                )->getNumberFormat()->setFormatCode('0');
                $sheet->getStyle(
                    Coordinate::stringFromColumnIndex($mutuStartIndex) . '5:' .
                    Coordinate::stringFromColumnIndex($bobotStartIndex - 1) . $highestRow
                )->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_GENERAL);
                $sheet->getStyle(
                    Coordinate::stringFromColumnIndex($bobotStartIndex) . '5:' .
                    Coordinate::stringFromColumnIndex($tailStartIndex - 1) . $highestRow
                )->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_GENERAL);
                $sheet->getStyle(
                    Coordinate::stringFromColumnIndex($tailStartIndex) . '5:' .
                    Coordinate::stringFromColumnIndex($tailStartIndex + $tailColumnCount - 2) . $highestRow
                )->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

                $sheet->getStyle('A2:' . $highestColumn . $highestRow)->getAlignment()->setWrapText(true);
            },
        ];
    }

    private function buildMetadataLabel(): string
    {
        $semesterKe = (int) ($this->template['metadata']['semester_ke'] ?? 0);
        $semesterLabel = (string) ($this->template['metadata']['semester_label'] ?? '-');
        $prodiLabel = (string) ($this->template['metadata']['prodi_label'] ?? '-');

        return sprintf(
            'FORMAT_IMPORT_KHS | SEMESTER_KE=%d | SEMESTER=%s | PRODI=%s',
            $semesterKe,
            $semesterLabel,
            $prodiLabel
        );
    }

    private function tailHeaders(): array
    {
        if ($this->usesSeparatedIpkColumn) {
            return [
                'group' => ['jml', 'ip', 'ipk', 'keterangan'],
                'header' => ['Jumlah', 'IP', 'IPK', 'Keterangan'],
            ];
        }

        return [
            'group' => ['jml', 'ip/ipk', 'keterangan'],
            'header' => ['Jumlah', 'IP/IPK', 'Keterangan'],
        ];
    }
}
