<?php

namespace App\Imports;

use App\Models\MasterData\MataKuliah;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Str;

class MataKuliahImport implements ToModel, WithHeadingRow, WithValidation
{
    protected $id_prodi;

    public function __construct($id_prodi)
    {
        $this->id_prodi = $id_prodi;
    }

    public function model(array $row)
    {
        // Hitung total SKS
        $totalSks = ($row['sks_tatap_muka'] ?? 0) + 
                   ($row['sks_praktikum'] ?? 0) + 
                   ($row['sks_praktek_lapangan'] ?? 0) + 
                   ($row['sks_simulasi'] ?? 0);

        return new MataKuliah([
            'id' => (string) Str::uuid(),
            'id_prodi' => $this->id_prodi,
            'kode_mk' => $row['kode_mk'],
            'nama_mk' => $row['nama_mk'],
            'sks_tatap_muka' => $row['sks_tatap_muka'] ?? 0,
            'sks_praktikum' => $row['sks_praktikum'] ?? 0,
            'sks_praktek_lapangan' => $row['sks_praktek_lapangan'] ?? 0,
            'sks_simulasi' => $row['sks_simulasi'] ?? 0,
            'sks' => $totalSks,
            'jenis_mk' => $row['jenis_mk'] ?? null,
            'kelompok_mk' => $row['kelompok_mk'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            'kode_mk' => 'required|string|max:20',
            'nama_mk' => 'required|string|max:255',
            'sks_tatap_muka' => 'nullable|integer|min:0',
            'sks_praktikum' => 'nullable|integer|min:0',
            'sks_praktek_lapangan' => 'nullable|integer|min:0',
            'sks_simulasi' => 'nullable|integer|min:0',
            'jenis_mk' => 'nullable|in:wajib_prodi,wajib_nasional,pilihan,peminatan,tugas_akhir/skripsi/tesis/disertasi',
            'kelompok_mk' => 'nullable|in:MPK,MKK,MKB,MPB,MBB,MKDK',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'kode_mk.required' => 'Kode Mata Kuliah wajib diisi',
            'kode_mk.max' => 'Kode Mata Kuliah maksimal 20 karakter',
            'nama_mk.required' => 'Nama Mata Kuliah wajib diisi',
            'nama_mk.max' => 'Nama Mata Kuliah maksimal 255 karakter',
            'jenis_mk.in' => 'Jenis Mata Kuliah tidak valid. Pilihan: wajib_prodi, wajib_nasional, pilihan, peminatan, tugas_akhir/skripsi/tesis/disertasi',
            'kelompok_mk.in' => 'Kelompok Mata Kuliah tidak valid. Pilihan: MPK, MKK, MKB, MPB, MBB, MKDK',
        ];
    }
}
