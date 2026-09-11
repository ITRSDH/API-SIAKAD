<?php

namespace App\Imports;

use App\Models\MasterData\Mahasiswa;
use App\Models\User;
use App\Services\StudentAngkatanResolverService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class MahasiswaImport implements ToModel, WithBatchInserts, WithChunkReading, WithHeadingRow, WithValidation
{
    private $errors = [];

    private $successCount = 0;

    private $rowCount = 0;

    private $idProdi;

    private StudentAngkatanResolverService $studentAngkatanResolverService;

    public function __construct($idProdi = null)
    {
        $this->idProdi = $idProdi;
        $this->studentAngkatanResolverService = app(StudentAngkatanResolverService::class);
    }

    public function model(array $row)
    {
        try {
            if (empty($row['nim'])) {
                return null;
            }

            $this->rowCount++;

            $nim = is_numeric($row['nim']) ? (string) $row['nim'] : trim($row['nim']);
            $nik = isset($row['nik']) ? (is_numeric($row['nik']) ? (string) $row['nik'] : ltrim(trim($row['nik']), "'")) : null;
            $nisn = isset($row['nisn']) ? (is_numeric($row['nisn']) ? (string) $row['nisn'] : trim($row['nisn'])) : null;
            $namaMahasiswa = $row['nama_lengkap'] ?? $row['nama_mahasiswa'] ?? $row['nama'] ?? 'Unknown';
            $status = $this->normalizeStatus($row['status_mahasiswa'] ?? $row['status'] ?? null);

            // 1. Parsing Tempat & Tanggal Lahir (Mendukung kolom terpisah maupun gabungan)
            $tempatLahir = $row['tempat_lahir'] ?? null;
            $tanggalLahir = $row['tanggal_lahir'] ?? null;

            if (empty($tempatLahir) && empty($tanggalLahir) && ! empty($row['tempat_tanggal_lahir'])) {
                $parts = explode(', ', $row['tempat_tanggal_lahir'], 2);
                $tempatLahir = $parts[0] ?? null;
                $tanggalLahir = $parts[1] ?? null;
            }

            $parsedTanggalLahir = ! empty($tanggalLahir) ? $this->parseDate($tanggalLahir) : null;
            $tanggalMasuk = ! empty($row['tanggal_masuk']) ? $this->parseDate($row['tanggal_masuk']) : null;

            // 2. Data Ibu Kandung
            $namaIbuKandung = $row['nama_ibu_kandung'] ?? $row['nama_ibu'] ?? $row['ibu_kandung'] ?? null;

            // 3. Kontak & Domisili
            $handphone = $row['no_handphone_wa'] ?? $row['no_handphone'] ?? $row['handphone'] ?? $row['no_hp'] ?? null;
            $emailPribadi = $row['email'] ?? $row['email_pribadi'] ?? null;
            $alamatJalan = $row['alamat_jalan'] ?? $row['alamat'] ?? null;
            $kelurahan = $row['kelurahan_desa'] ?? $row['kelurahan'] ?? null;
            $idWilayah = $row['kecamatan_wilayah'] ?? $row['kecamatan'] ?? $row['id_wilayah'] ?? null;

            // 4. Jenis Kelamin
            $jenisKelamin = null;
            if (! empty($row['jenis_kelamin'])) {
                $jk = strtoupper(trim($row['jenis_kelamin']));
                if (in_array($jk, ['L', 'P', 'LAKI-LAKI', 'PEREMPUAN', 'MALE', 'FEMALE'])) {
                    $jenisKelamin = in_array($jk, ['L', 'LAKI-LAKI', 'MALE']) ? 'L' : 'P';
                }
            }

            // 5. Agama
            $agama = null;
            if (! empty($row['agama'])) {
                $agamaValue = ucfirst(strtolower(trim($row['agama'])));
                $validAgama = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'];
                if (in_array($agamaValue, $validAgama)) {
                    $agama = $agamaValue;
                }
            }

            // 6. Jenis Pendaftaran (Reguler vs RPL vs Pindahan)
            $jenisPendaftaran = $this->normalizeJenisPendaftaran($row['jenis_pendaftaran'] ?? null, $nim);
            $kampusAsal = $row['kampus_asal_rpl'] ?? $row['perguruan_tinggi_asal'] ?? $row['kampus_asal'] ?? null;
            $prodiAsal = $row['prodi_asal_rpl'] ?? $row['prodi_asal'] ?? null;
            $sksDiakui = isset($row['sks_diakui_rpl']) ? (int) $row['sks_diakui_rpl'] : (isset($row['sks_diakui']) ? (int) $row['sks_diakui'] : 0);

            // 7. Orang Tua (Pekerjaan Ibu & Data Ayah)
            $pekerjaanIbu = $row['pekerjaan_ibu'] ?? $row['profesi_ibu'] ?? null;
            $namaAyah = $row['nama_ayah'] ?? null;
            $pekerjaanAyah = $row['pekerjaan_ayah'] ?? $row['profesi_ayah'] ?? null;

            // Password default akun mahasiswa
            $password = '12345678';

            DB::transaction(function () use (
                $nim, $nik, $nisn, $namaMahasiswa, $status, $tempatLahir, $parsedTanggalLahir,
                $tanggalMasuk, $password, $jenisKelamin, $agama, $alamatJalan, $kelurahan, $idWilayah,
                $namaIbuKandung, $pekerjaanIbu, $namaAyah, $pekerjaanAyah, $handphone, $emailPribadi,
                $jenisPendaftaran, $kampusAsal, $prodiAsal, $sksDiakui, $row
            ) {
                $angkatan = $this->resolveAngkatan($row);
                if ($angkatan === null) {
                    throw new \RuntimeException(
                        sprintf(
                            'NIM %s tidak dapat digunakan untuk menentukan angkatan. Isi kolom angkatan secara manual.',
                            $nim
                        )
                    );
                }

                // 1. Buat User akun mahasiswa jika belum ada
                $user = User::create([
                    'name' => $namaMahasiswa,
                    'email' => $emailPribadi,
                    'password' => Hash::make($password),
                    'status' => $status === 'Aktif' ? 'aktif' : 'tidak-aktif',
                ]);

                // 2. Assign role mahasiswa
                $user->assignRole('mahasiswa');

                // 3. Buat data Mahasiswa lengkap
                Mahasiswa::create([
                    'nim' => $nim,
                    'nik' => $nik,
                    'nisn' => $nisn,
                    'nama_mahasiswa' => $namaMahasiswa,
                    'jenis_kelamin' => $jenisKelamin,
                    'tempat_lahir' => $tempatLahir,
                    'tanggal_lahir' => $parsedTanggalLahir,
                    'tanggal_masuk' => $tanggalMasuk,
                    'alamat' => $alamatJalan,
                    'alamat_jalan' => $alamatJalan,
                    'kelurahan' => $kelurahan,
                    'id_wilayah' => $idWilayah,
                    'nama_ibu_kandung' => $namaIbuKandung,
                    'pekerjaan_ibu' => $pekerjaanIbu,
                    'nama_ayah' => $namaAyah,
                    'pekerjaan_ayah' => $pekerjaanAyah,
                    'handphone' => $handphone,
                    'email_pribadi' => $emailPribadi,
                    'agama' => $agama,
                    'status' => $status,
                    'angkatan' => $angkatan,
                    'id_prodi' => $this->idProdi,
                    'user_id' => $user->id,
                    'jenis_pendaftaran' => $jenisPendaftaran,
                    'perguruan_tinggi_asal' => $kampusAsal,
                    'prodi_asal' => $prodiAsal,
                    'sks_diakui' => $sksDiakui,
                    'sync_status' => 'belum',
                ]);
            });

            $this->successCount++;
        } catch (\Exception $e) {
            $this->errors[] = "Baris {$this->rowCount} (NIM {$row['nim']}): ".$e->getMessage();

            return null;
        }

        return null;
    }

    public function rules(): array
    {
        return [
            'nim' => 'nullable|max:25|unique:mahasiswa,nim',
            'nik' => 'nullable|max:20',
            'nama_lengkap' => 'nullable|string|max:255',
            'nama_mahasiswa' => 'nullable|string|max:255',
            'jenis_kelamin' => 'nullable|string',
            'agama' => 'nullable|string',
            'nama_ibu_kandung' => 'nullable|string|max:255',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'nim.unique' => 'NIM sudah terdaftar di sistem',
            'nim.max' => 'NIM maksimal 25 karakter',
            'nik.max' => 'NIK maksimal 20 karakter',
        ];
    }

    private function parseDate($date)
    {
        if (empty($date)) {
            return null;
        }

        try {
            if (is_numeric($date)) {
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($date))->format('Y-m-d');
            }

            if (is_string($date)) {
                $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd-M-y', 'd-M-Y', 'Y/m/d', 'D-M-yy', 'D-MMM-YY'];
                foreach ($formats as $format) {
                    try {
                        return Carbon::createFromFormat($format, trim($date))->format('Y-m-d');
                    } catch (\Exception $e) {
                        continue;
                    }
                }

                return Carbon::parse(trim($date))->format('Y-m-d');
            }

            if ($date instanceof \DateTime) {
                return Carbon::instance($date)->format('Y-m-d');
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function batchSize(): int
    {
        return 20;
    }

    public function chunkSize(): int
    {
        return 50;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getSuccessCount()
    {
        return $this->successCount;
    }

    public function getRowCount()
    {
        return $this->rowCount;
    }

    private function resolveAngkatan(array $row): ?int
    {
        $manualAngkatan = isset($row['angkatan']) ? (string) $row['angkatan'] : null;
        $nim = isset($row['nim']) ? (string) $row['nim'] : null;

        return $this->studentAngkatanResolverService->resolve($manualAngkatan, $nim);
    }

    private function normalizeStatus(?string $status): string
    {
        $normalized = strtoupper(trim((string) $status));

        return match ($normalized) {
            'AKTIF' => 'Aktif',
            'CUTI' => 'Cuti',
            'DO' => 'DO',
            'LULUS' => 'Lulus',
            default => 'Aktif',
        };
    }

    private function normalizeJenisPendaftaran(?string $jenis, ?string $nim = null): string
    {
        // 1. Prioritas deteksi akhiran NIM 'B' atau 'b' (standar RPL STIKES Dian Husada)
        if (! empty($nim) && str_ends_with(strtoupper(trim((string) $nim)), 'B')) {
            return 'RPL';
        }

        // 2. Normalisasi input teks jika ada
        $normalized = strtoupper(trim((string) $jenis));

        return match ($normalized) {
            'RPL', 'REKOGNISI', 'ALIH JENJANG' => 'RPL',
            'PINDAHAN', 'PINDAH' => 'Pindahan',
            default => 'Reguler',
        };
    }
}
