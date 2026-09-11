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

class MahasiswaExport implements FromCollection, WithColumnFormatting, WithEvents, WithHeadings, WithMapping
{
    protected $id_prodi;

    protected $isDummy;

    protected $jenis_pendaftaran;

    public function __construct($id_prodi = null, $isDummy = false, $jenis_pendaftaran = null)
    {
        $this->id_prodi = $id_prodi;
        $this->isDummy = $isDummy;
        $this->jenis_pendaftaran = $jenis_pendaftaran;
    }

    public function collection()
    {
        if ($this->isDummy) {
            return collect([
                (object) [
                    'nim' => '202401001',
                    'nama_mahasiswa' => 'Ahmad Rizki',
                    'nik' => "'3201234567890001",
                    'nisn' => '0051234567',
                    'jenis_kelamin' => 'L',
                    'tempat_lahir' => 'Jakarta',
                    'tanggal_lahir' => '2005-01-15',
                    'nama_ibu_kandung' => 'Siti Aminah',
                    'pekerjaan_ibu' => 'Ibu Rumah Tangga',
                    'nama_ayah' => 'Bambang Sutrisno',
                    'pekerjaan_ayah' => 'PNS/TNI/Polri',
                    'agama' => 'Islam',
                    'handphone' => '081234567890',
                    'email_pribadi' => 'ahmad.rizki@example.com',
                    'alamat_jalan' => 'Jl. Merdeka No. 123',
                    'kelurahan' => 'Gambir',
                    'id_wilayah' => 'Kec. Gambir',
                    'tanggal_masuk' => '2024-09-01',
                    'angkatan' => 2024,
                    'status' => 'Aktif',
                    'jenis_pendaftaran' => 'Reguler',
                    'perguruan_tinggi_asal' => null,
                    'prodi_asal' => null,
                    'sks_diakui' => 0,
                ],
                (object) [
                    'nim' => '202401002',
                    'nama_mahasiswa' => 'Siti Nurhaliza, A.Md.Kep',
                    'nik' => "'3201234567890002",
                    'nisn' => null,
                    'jenis_kelamin' => 'P',
                    'tempat_lahir' => 'Bandung',
                    'tanggal_lahir' => '1998-03-20',
                    'nama_ibu_kandung' => 'Fatimah Zahra',
                    'pekerjaan_ibu' => 'Karyawan Swasta',
                    'nama_ayah' => 'Cecep Supriatna',
                    'pekerjaan_ayah' => 'Wiraswasta',
                    'agama' => 'Islam',
                    'handphone' => '081298765432',
                    'email_pribadi' => 'siti.nurhaliza@example.com',
                    'alamat_jalan' => 'Jl. Sudirman No. 456',
                    'kelurahan' => 'Cibabat',
                    'id_wilayah' => 'Kec. Cimahi Utara',
                    'tanggal_masuk' => '2024-09-01',
                    'angkatan' => 2024,
                    'status' => 'Aktif',
                    'jenis_pendaftaran' => 'RPL',
                    'perguruan_tinggi_asal' => 'Akper Dustira Cimahi',
                    'prodi_asal' => 'D3 Keperawatan',
                    'sks_diakui' => 84,
                ],
                (object) [
                    'nim' => '202401003',
                    'nama_mahasiswa' => 'Budi Santoso',
                    'nik' => "'3201234567890003",
                    'nisn' => '0041234568',
                    'jenis_kelamin' => 'L',
                    'tempat_lahir' => 'Surabaya',
                    'tanggal_lahir' => '2004-05-10',
                    'nama_ibu_kandung' => 'Sri Wahyuni',
                    'pekerjaan_ibu' => 'PNS/TNI/Polri',
                    'nama_ayah' => 'Joko Widodo',
                    'pekerjaan_ayah' => 'Pensiunan',
                    'agama' => 'Kristen',
                    'handphone' => '081345678901',
                    'email_pribadi' => 'budi.santoso@example.com',
                    'alamat_jalan' => 'Jl. Gajah Mada No. 789',
                    'kelurahan' => 'Sawahan',
                    'id_wilayah' => 'Kec. Sawahan',
                    'tanggal_masuk' => '2024-09-01',
                    'angkatan' => 2024,
                    'status' => 'Aktif',
                    'jenis_pendaftaran' => 'Pindahan',
                    'perguruan_tinggi_asal' => 'STIKES Mitra Keluarga',
                    'prodi_asal' => 'S1 Keperawatan',
                    'sks_diakui' => 42,
                ],
            ]);
        }

        $query = Mahasiswa::with(['prodi'])
            ->select([
                'nim',
                'nama_mahasiswa',
                'nik',
                'nisn',
                'jenis_kelamin',
                'tempat_lahir',
                'tanggal_lahir',
                'nama_ibu_kandung',
                'pekerjaan_ibu',
                'nama_ayah',
                'pekerjaan_ayah',
                'agama',
                'handphone',
                'email_pribadi',
                'alamat',
                'alamat_jalan',
                'kelurahan',
                'id_wilayah',
                'tanggal_masuk',
                'angkatan',
                'status',
                'jenis_pendaftaran',
                'perguruan_tinggi_asal',
                'prodi_asal',
                'sks_diakui',
                'id_prodi',
            ]);

        if ($this->id_prodi) {
            $query->where('id_prodi', $this->id_prodi);
        }

        if ($this->jenis_pendaftaran) {
            $query->where('jenis_pendaftaran', $this->jenis_pendaftaran);
        }

        return $query->orderBy('nim')->get();
    }

    public function headings(): array
    {
        return [
            'NIM',
            'NAMA LENGKAP',
            'NIK',
            'NISN',
            'JENIS KELAMIN',
            'TEMPAT LAHIR',
            'TANGGAL LAHIR',
            'NAMA IBU KANDUNG',
            'PEKERJAAN IBU',
            'NAMA AYAH',
            'PEKERJAAN AYAH',
            'AGAMA',
            'NO HANDPHONE / WA',
            'EMAIL',
            'ALAMAT JALAN',
            'KELURAHAN / DESA',
            'KECAMATAN / WILAYAH',
            'TANGGAL MASUK',
            'ANGKATAN',
            'STATUS MAHASISWA',
            'JENIS PENDAFTARAN',
            'KAMPUS ASAL (RPL)',
            'PRODI ASAL (RPL)',
            'SKS DIAKUI (RPL)',
        ];
    }

    public function map($mahasiswa): array
    {
        return [
            $mahasiswa->nim,
            $mahasiswa->nama_mahasiswa,
            $mahasiswa->nik ? "'".ltrim($mahasiswa->nik, "'") : null,
            $mahasiswa->nisn,
            $mahasiswa->jenis_kelamin,
            $mahasiswa->tempat_lahir,
            $mahasiswa->tanggal_lahir
                ? (is_string($mahasiswa->tanggal_lahir) ? $mahasiswa->tanggal_lahir : Carbon::parse($mahasiswa->tanggal_lahir)->format('Y-m-d'))
                : null,
            $mahasiswa->nama_ibu_kandung,
            $mahasiswa->pekerjaan_ibu,
            $mahasiswa->nama_ayah,
            $mahasiswa->pekerjaan_ayah,
            $mahasiswa->agama,
            $mahasiswa->handphone,
            $mahasiswa->email_pribadi,
            $mahasiswa->alamat_jalan ?? $mahasiswa->alamat,
            $mahasiswa->kelurahan,
            $mahasiswa->id_wilayah,
            $mahasiswa->tanggal_masuk
                ? (is_string($mahasiswa->tanggal_masuk) ? $mahasiswa->tanggal_masuk : Carbon::parse($mahasiswa->tanggal_masuk)->format('Y-m-d'))
                : null,
            $mahasiswa->angkatan,
            $mahasiswa->status ?? 'Aktif',
            $mahasiswa->jenis_pendaftaran ?? 'Reguler',
            $mahasiswa->perguruan_tinggi_asal,
            $mahasiswa->prodi_asal,
            $mahasiswa->sks_diakui ?? 0,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => '@', // NIM as text
            'C' => '@', // NIK as text
            'D' => '@', // NISN as text
            'J' => '@', // HP as text
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                // Apply borders & alignment to all data cells
                $sheet->getStyle('A1:'.$highestColumn.$highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => 'D0D5DD'],
                        ],
                    ],
                ]);

                // Header styling
                $sheet->getStyle('A1:'.$highestColumn.'1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 11,
                        'color' => ['rgb' => '1E293B'],
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E0F2FE'], // light blue
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getRowDimension(1)->setRowHeight(28);

                // Auto-fit column widths
                foreach (range('A', $highestColumn) as $columnID) {
                    $sheet->getColumnDimension($columnID)->setAutoSize(true);
                }
            },
        ];
    }
}
