<?php

namespace App\Exports;

use App\Models\MasterData\MataKuliah;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class MataKuliahExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    protected $id_prodi;
    protected $isDummy;

    public function __construct($id_prodi, $isDummy = false)
    {
        $this->id_prodi = $id_prodi;
        $this->isDummy = $isDummy;
    }

    public function collection()
    {
        if ($this->isDummy) {
            return collect([
                (object) [
                    'kode_mk' => 'IF001',
                    'nama_mk' => 'Algoritma dan Pemrograman',
                    'sks_tatap_muka' => 2,
                    'sks_praktikum' => 1,
                    'sks_praktek_lapangan' => 0,
                    'sks_simulasi' => 0,
                    'jenis_mk' => 'wajib_prodi',
                    'kelompok_mk' => 'MKK',
                ],
                (object) [
                    'kode_mk' => 'IF002',
                    'nama_mk' => 'Struktur Data',
                    'sks_tatap_muka' => 2,
                    'sks_praktikum' => 1,
                    'sks_praktek_lapangan' => 0,
                    'sks_simulasi' => 0,
                    'jenis_mk' => 'wajib_prodi',
                    'kelompok_mk' => 'MKK',
                ],
                (object) [
                    'kode_mk' => 'IF003',
                    'nama_mk' => 'Basis Data',
                    'sks_tatap_muka' => 2,
                    'sks_praktikum' => 2,
                    'sks_praktek_lapangan' => 0,
                    'sks_simulasi' => 0,
                    'jenis_mk' => 'wajib_prodi',
                    'kelompok_mk' => 'MKB',
                ]
            ]);
        }

        return MataKuliah::where('id_prodi', $this->id_prodi)
            ->select([
                'kode_mk',
                'nama_mk',
                'sks_tatap_muka',
                'sks_praktikum',
                'sks_praktek_lapangan',
                'sks_simulasi',
                'jenis_mk',
                'kelompok_mk',
            ])
            ->orderBy('kode_mk')
            ->get();
    }

    public function headings(): array
    {
        return [
            'kode_mk',
            'nama_mk',
            'sks_tatap_muka',
            'sks_praktikum',
            'sks_praktek_lapangan',
            'sks_simulasi',
            'jenis_mk',
            'kelompok_mk',
        ];
    }

    public function map($mataKuliah): array
    {
        return [
            $mataKuliah->kode_mk,
            $mataKuliah->nama_mk,
            $mataKuliah->sks_tatap_muka ?? 0,
            $mataKuliah->sks_praktikum ?? 0,
            $mataKuliah->sks_praktek_lapangan ?? 0,
            $mataKuliah->sks_simulasi ?? 0,
            $mataKuliah->jenis_mk,
            $mataKuliah->kelompok_mk,
        ];
    }

    public function registerEvents(): array
{
    return [
        AfterSheet::class => function (AfterSheet $event) {

            $sheet = $event->sheet->getDelegate();

            $jenisMkOptions = [
                'wajib_prodi',
                'wajib_nasional',
                'pilihan',
                'peminatan',
                'tugas_akhir/skripsi/tesis/disertasi'
            ];

            $kelompokMkOptions = [
                'MPK',
                'MKK',
                'MKB',
                'MPB',
                'MBB',
                'MKDK'
            ];

            // Apply sampai 1000 baris
            for ($row = 2; $row <= 1000; $row++) {

                // Kolom G = jenis_mk
                $validationJenis = $sheet->getCell("G{$row}")->getDataValidation();
                $validationJenis->setType(DataValidation::TYPE_LIST);
                $validationJenis->setErrorStyle(DataValidation::STYLE_STOP);
                $validationJenis->setAllowBlank(true);
                $validationJenis->setShowDropDown(true);
                $validationJenis->setFormula1('"' . implode(',', $jenisMkOptions) . '"');

                // Kolom H = kelompok_mk
                $validationKelompok = $sheet->getCell("H{$row}")->getDataValidation();
                $validationKelompok->setType(DataValidation::TYPE_LIST);
                $validationKelompok->setErrorStyle(DataValidation::STYLE_STOP);
                $validationKelompok->setAllowBlank(true);
                $validationKelompok->setShowDropDown(true);
                $validationKelompok->setFormula1('"' . implode(',', $kelompokMkOptions) . '"');
            }
        },
    ];
}
}
