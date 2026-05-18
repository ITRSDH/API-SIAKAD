<?php

namespace App\Imports;

use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\Kurikulum;
use App\Models\MasterData\RiwayatKurikulumMahasiswa;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class MahasiswaImport implements ToModel, WithHeadingRow, WithValidation, WithBatchInserts, WithChunkReading
{
    private $errors = [];
    private $successCount = 0;
    private $rowCount = 0;
    private $idProdi;

    public function __construct($idProdi = null)
    {
        $this->idProdi = $idProdi;
    }

    public function model(array $row)
    {
        try {
            if (empty($row['nim'])) {
                return null;
            }

            $this->rowCount++;

            $nim = is_numeric($row['nim']) ? (string) $row['nim'] : $row['nim'];
            $nik = isset($row['nik']) ? (is_numeric($row['nik']) ? (string) $row['nik'] : ltrim($row['nik'], "'")) : null;
            $namaMahasiswa = $row['nama_mahasiswa'] ?? $row['nama'] ?? 'Unknown';
            $status = $row['status_mahasiswa'] ?? 'Aktif';

            // Parse tempat dan tanggal lahir
            $tempatLahir = null;
            $tanggalLahir = null;
            if (!empty($row['tempat_tanggal_lahir'])) {
                $parts = explode(', ', $row['tempat_tanggal_lahir'], 2);
                $tempatLahir = $parts[0] ?? null;
                $tanggalLahir = $parts[1] ?? null;
            }

            $tanggalMasuk = !empty($row['tanggal_masuk']) ? $this->parseDate($row['tanggal_masuk']) : null;
            $parsedTanggalLahir = !empty($tanggalLahir) ? $this->parseDate($tanggalLahir) : null;

            // Handle jenis_kelamin dengan lebih fleksibel
            $jenisKelamin = null;
            if (!empty($row['jenis_kelamin'])) {
                $jk = strtoupper(trim($row['jenis_kelamin']));
                if (in_array($jk, ['L', 'P', 'LAKI-LAKI', 'PEREMPUAN', 'MALE', 'FEMALE'])) {
                    $jenisKelamin = (in_array($jk, ['L', 'LAKI-LAKI', 'MALE'])) ? 'L' : 'P';
                }
            }

            // Handle agama dengan lebih fleksibel
            $agama = null;
            if (!empty($row['agama'])) {
                $agamaValue = ucfirst(strtolower(trim($row['agama'])));
                $validAgama = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'];
                if (in_array($agamaValue, $validAgama)) {
                    $agama = $agamaValue;
                }
            }

            // Handle alamat dengan cleaning
            $alamat = null;
            if (!empty($row['alamat'])) {
                $alamat = trim($row['alamat']);
            }

            // Generate password default (NIM atau tanggal lahir)
            $password = '12345678';
            // if (!empty($parsedTanggalLahir)) {
            //     $password = $parsedTanggalLahir->format('dmY');
            // }

            DB::transaction(function () use ($nim, $nik, $namaMahasiswa, $status, $tempatLahir, $parsedTanggalLahir, $tanggalMasuk, $password, $jenisKelamin, $agama, $alamat, $row) {
                $angkatan = $this->resolveAngkatan($row, $tanggalMasuk);
                $resolvedKurikulumId = $this->resolveKurikulumId($this->idProdi, $angkatan, $tanggalMasuk);

                if (!$resolvedKurikulumId) {
                    throw new \RuntimeException('Kurikulum aktif untuk program studi mahasiswa belum tersedia.');
                }

                // 1. Buat User terlebih dahulu
                $user = User::create([
                    'name' => $namaMahasiswa,
                    // 'email' => strtolower(str_replace(' ', '.', $namaMahasiswa)) . '@example.com',
                    'email' => null,
                    'password' => Hash::make($password),
                    'status' => $status === 'Aktif' ? 'aktif' : 'tidak-aktif'
                ]);

                // 2. Assign role "mahasiswa" ke user
                $user->assignRole('mahasiswa');

                // 3. Buat Mahasiswa dengan menghubungkan ke user yang baru dibuat
                $mahasiswa = Mahasiswa::create([
                    // 'id' => (string) Str::uuid(),
                    'nim' => $nim,
                    'nik' => $nik,
                    'nama_mahasiswa' => $namaMahasiswa,
                    'jenis_kelamin' => $jenisKelamin,
                    'tempat_lahir' => $tempatLahir,
                    'tanggal_lahir' => $parsedTanggalLahir,
                    'tanggal_masuk' => $tanggalMasuk,
                    'alamat' => $alamat,
                    'agama' => $agama,
                    'status' => $status,
                    'angkatan' => $angkatan,
                    'id_prodi' => $this->idProdi,
                    'id_kurikulum' => $resolvedKurikulumId,
                    'user_id' => $user->id,
                ]);
                RiwayatKurikulumMahasiswa::create([
                    'id_mahasiswa' => $mahasiswa->id,
                    'id_kurikulum' => $resolvedKurikulumId,
                    'tanggal_mulai' => $tanggalMasuk?->toDateString() ?? now()->toDateString(),
                    'tanggal_selesai' => null,
                    'is_active' => true,
                    'catatan' => 'Kurikulum awal mahasiswa hasil import',
                    'created_by' => null,
                ]);
            });

            $this->successCount++;
        } catch (\Exception $e) {
            $this->errors[] = "Baris {$this->rowCount}: " . $e->getMessage();
            return null;
        }

        return null;
    }

    public function rules(): array
    {
        return [
            'nim' => 'nullable|max:20|unique:mahasiswa,nim',
            'nik' => 'nullable|max:20|unique:mahasiswa,nik',
            'nama_mahasiswa' => 'nullable|string|max:255',
            'program_studi' => 'nullable|string|max:255',
            'jenis_kelamin' => 'nullable|string',
            'tempat_tanggal_lahir' => 'nullable|string|max:255',
            'tanggal_masuk' => 'nullable',
            'alamat' => 'nullable|string',
            'agama' => 'nullable|string',
            'status_mahasiswa' => 'nullable|in:Aktif,Cuti,DO,Lulus',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'nim.max' => 'NIM maksimal 20 karakter',
            'nim.unique' => 'NIM sudah terdaftar',
            'nik.max' => 'NIK maksimal 20 karakter',
            'nik.unique' => 'NIK sudah terdaftar',
            'nama_mahasiswa.max' => 'Nama Mahasiswa maksimal 255 karakter',
            'program_studi.max' => 'Program Studi maksimal 255 karakter',
            'jenis_kelamin.in' => 'Jenis Kelamin tidak valid. Pilihan: L, P',
            'tempat_tanggal_lahir.max' => 'Tempat, Tanggal Lahir maksimal 255 karakter',
            'agama.in' => 'Agama tidak valid. Pilihan: Islam, Kristen, Katolik, Hindu, Buddha, Konghucu',
            'status_mahasiswa.in' => 'Status Mahasiswa tidak valid. Pilihan: Aktif, Cuti, DO, Lulus',
        ];
    }

    private function parseDate($date)
    {
        if (empty($date)) {
            return null;
        }

        try {
            // Handle Excel numeric date format
            if (is_numeric($date)) {
                return \Carbon\Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($date));
            }

            // Handle string dates with various formats
            if (is_string($date)) {
                // Try common date formats including Excel format D-MMM-YY
                $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd-M-y', 'd-M-Y', 'Y/m/d', 'D-M-yy', 'D-MMM-YY'];

                foreach ($formats as $format) {
                    try {
                        return \Carbon\Carbon::createFromFormat($format, $date);
                    } catch (\Exception $e) {
                        // Continue to next format
                        continue;
                    }
                }

                // If all formats fail, try flexible parsing
                return \Carbon\Carbon::parse($date);
            }

            // Handle DateTime objects
            if ($date instanceof \DateTime) {
                return \Carbon\Carbon::instance($date);
            }

            return null;
        } catch (\Exception $e) {
            // Return null instead of throwing exception to avoid breaking import
            return null;
        }
    }

    public function batchSize(): int
    {
        return 10;
    }

    public function chunkSize(): int
    {
        return 10;
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

    private function resolveAngkatan(array $row, $tanggalMasuk): ?int
    {
        if (!empty($row['angkatan']) && is_numeric($row['angkatan'])) {
            return (int) $row['angkatan'];
        }

        if ($tanggalMasuk instanceof \Carbon\Carbon) {
            return (int) $tanggalMasuk->format('Y');
        }

        return null;
    }

    private function resolveKurikulumId(?string $prodiId, ?int $angkatan, $tanggalMasuk): ?string
    {
        if (!$prodiId) {
            return null;
        }

        $cohortSortKey = $this->resolveCohortSortKey($angkatan, $tanggalMasuk);

        $kurikulums = Kurikulum::with('semesterMulai.tahunAkademik')
            ->where('id_prodi', $prodiId)
            ->get();

        if ($kurikulums->isEmpty()) {
            return null;
        }

        $sorted = $kurikulums->sortByDesc(fn(Kurikulum $kurikulum) => $this->buildKurikulumSortKey($kurikulum) ?? 0)
            ->values();

        $preferredSemesterOrder = $this->resolvePreferredSemesterOrder($angkatan, $tanggalMasuk);

        if ($cohortSortKey !== null) {
            $matched = $sorted->first(function (Kurikulum $kurikulum) use ($cohortSortKey) {
                $kurikulumSortKey = $this->buildKurikulumSortKey($kurikulum);

                return $kurikulumSortKey !== null && $kurikulumSortKey <= $cohortSortKey;
            });

            if ($matched) {
                $eligible = $sorted->filter(function (Kurikulum $kurikulum) use ($cohortSortKey) {
                    $kurikulumSortKey = $this->buildKurikulumSortKey($kurikulum);

                    return $kurikulumSortKey !== null && $kurikulumSortKey <= $cohortSortKey;
                })->values();

                return $this->resolvePreferredKurikulumCandidate($eligible, $preferredSemesterOrder)?->id;
            }
        }

        return $this->resolvePreferredKurikulumCandidate($sorted, $preferredSemesterOrder)?->id;
    }

    private function resolveCohortSortKey(?int $angkatan, $tanggalMasuk): ?int
    {
        if ($tanggalMasuk instanceof \Carbon\Carbon) {
            $year = (int) $tanggalMasuk->format('Y');
            $month = (int) $tanggalMasuk->format('n');
            $semesterOrder = $month >= 7 ? 1 : 2;
            $academicStartYear = $semesterOrder === 1 ? $year : $year - 1;

            return ($academicStartYear * 10) + $semesterOrder;
        }

        if ($angkatan !== null) {
            return ($angkatan * 10) + 1;
        }

        return null;
    }

    private function resolvePreferredKurikulumCandidate($kurikulums, ?int $preferredSemesterOrder): ?Kurikulum
    {
        if ($kurikulums->isEmpty()) {
            return null;
        }

        if ($preferredSemesterOrder !== null) {
            $preferred = $kurikulums->first(function (Kurikulum $kurikulum) use ($preferredSemesterOrder) {
                return $this->resolveSemesterOrder(
                    $kurikulum->semesterMulai?->kode_semester,
                    $kurikulum->semesterMulai?->nama_semester
                ) === $preferredSemesterOrder;
            });

            if ($preferred) {
                return $preferred;
            }
        }

        return $kurikulums->first();
    }

    private function resolvePreferredSemesterOrder(?int $angkatan, $tanggalMasuk): ?int
    {
        $cohortSortKey = $this->resolveCohortSortKey($angkatan, $tanggalMasuk);

        return $cohortSortKey !== null ? (int) substr((string) $cohortSortKey, -1) : null;
    }

    private function buildKurikulumSortKey(Kurikulum $kurikulum): ?int
    {
        $tahunAkademik = $kurikulum->semesterMulai?->tahunAkademik?->tahun_akademik;
        if (!$tahunAkademik) {
            return null;
        }

        $tahunMulai = (int) substr((string) $tahunAkademik, 0, 4);
        $semesterOrder = $this->resolveSemesterOrder(
            $kurikulum->semesterMulai?->kode_semester,
            $kurikulum->semesterMulai?->nama_semester
        );

        return ($tahunMulai * 10) + $semesterOrder;
    }

    private function resolveSemesterOrder(?string $kodeSemester = null, ?string $namaSemester = null): int
    {
        $normalizedKode = strtolower(trim((string) $kodeSemester));
        $normalizedNama = strtolower(trim((string) $namaSemester));

        if (str_contains($normalizedKode, 'ganjil') || str_contains($normalizedNama, 'ganjil') || $normalizedKode === '1') {
            return 1;
        }

        if (str_contains($normalizedKode, 'genap') || str_contains($normalizedNama, 'genap') || $normalizedKode === '2') {
            return 2;
        }

        return 9;
    }
}
