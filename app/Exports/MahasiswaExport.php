<?php

namespace App\Exports;

use App\Models\MasterData\Mahasiswa;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class MahasiswaExport implements FromCollection, WithHeadings, WithMapping, WithEvents, WithColumnFormatting
{
    protected $id_prodi;
    protected $isDummy;

    public function __construct($id_prodi = null, $isDummy = false)
    {
        $this->id_prodi = $id_prodi;
        $this->isDummy = $isDummy;
    }

    public function collection()
    {
        if ($this->isDummy) {
            return collect([
                (object) [
                    'nim' => '202100001',
                    'nik' => "'1234567890123456",
                    'nama_mahasiswa' => 'Ahmad Rizki',
                    'jenis_kelamin' => 'L',
                    'tempat_lahir' => 'Jakarta',
                    'tanggal_lahir' => '2000-01-15',
                    'tanggal_masuk' => '2021-08-01',
                    'alamat' => 'Jl. Merdeka No. 123, Jakarta',
                    'agama' => 'Islam',
                    'status' => 'Aktif',
                ],
                (object) [
                    'nim' => '202100002',
                    'nik' => "'1234567890123457",
                    'nama_mahasiswa' => 'Siti Nurhaliza',
                    'jenis_kelamin' => 'P',
                    'tempat_lahir' => 'Bandung',
                    'tanggal_lahir' => '2000-03-20',
                    'tanggal_masuk' => '2021-08-01',
                    'alamat' => 'Jl. Sudirman No. 456, Bandung',
                    'agama' => 'Islam',
                    'status' => 'Aktif',
                ],
                (object) [
                    'nim' => '202100003',
                    'nik' => "'1234567890123458",
                    'nama_mahasiswa' => 'Budi Santoso',
                    'jenis_kelamin' => 'L',
                    'tempat_lahir' => 'Surabaya',
                    'tanggal_lahir' => '2000-05-10',
                    'tanggal_masuk' => '2021-08-01',
                    'alamat' => 'Jl. Gajah Mada No. 789, Surabaya',
                    'agama' => 'Kristen',
                    'status' => 'Cuti',
                ]
            ]);
        }

        $query = Mahasiswa::with(['prodi'])
            ->select([
                'nim',
                'nik',
                'nama_mahasiswa',
                'jenis_kelamin',
                'tempat_lahir',
                'tanggal_lahir',
                'tanggal_masuk',
                'alamat',
                'agama',
                'status',
                'id_prodi',
            ]);

        if ($this->id_prodi) {
            $query->where('id_prodi', $this->id_prodi);
        }

        return $query->orderBy('nim')->get();
    }

    public function headings(): array
    {
        return [
            'NIM',
            'NIK',
            'NAMA',
            'TANGGAL MASUK',
            'STATUS MAHASISWA',
            'JENIS KELAMIN',
            'TEMPAT, TANGGAL LAHIR',
            'AGAMA',
            'ALAMAT',
        ];
    }

    public function map($mahasiswa): array
    {
        return [
            $mahasiswa->nim,
            $mahasiswa->nik,
            $mahasiswa->nama_mahasiswa,

            // TANGGAL MASUK (Kolom D)
            $mahasiswa->tanggal_masuk
                ? Date::dateTimeToExcel(Carbon::parse($mahasiswa->tanggal_masuk))
                : null,
            $mahasiswa->status,
            $mahasiswa->jenis_kelamin,

            // TEMPAT, TANGGAL LAHIR (kolom G)
            $mahasiswa->tanggal_lahir
                ? $mahasiswa->tempat_lahir . ', ' .
                Carbon::parse($mahasiswa->tanggal_lahir)->translatedFormat('d F Y')
                : $mahasiswa->tempat_lahir,

            $mahasiswa->agama,
            $mahasiswa->alamat,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => 'D-MMM-YY',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Get the highest row and column
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();
                $columnCount = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

                // Apply styles to all cells
                $sheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray([
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // Apply bold to header row (row 1)
                $sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E6F3FF'],
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Set column widths
                foreach (range('A', $highestColumn) as $columnID) {
                    $sheet->getColumnDimension($columnID)->setAutoSize(true);
                }

            },
        ];
    }
}
