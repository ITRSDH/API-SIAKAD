<?php

namespace App\Services;

use App\Models\MasterData\Kurikulum;
use App\Models\MasterData\KurikulumInduk;
use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\RefJenisKurikulum;
use App\Models\MasterData\Semester;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MahasiswaCurriculumContextService
{
    public function resolveMahasiswaKurikulumId(Mahasiswa|string|null $mahasiswa): ?string
    {
        $resolvedMahasiswa = $this->resolveMahasiswa($mahasiswa);
        if (!$resolvedMahasiswa) {
            return null;
        }

        if ($resolvedMahasiswa->relationLoaded('riwayatKurikulumAktif')) {
            $activeHistoryId = $resolvedMahasiswa->riwayatKurikulumAktif?->id_kurikulum;
            if (filled($activeHistoryId)) {
                return $activeHistoryId;
            }
        }

        if ($resolvedMahasiswa->relationLoaded('riwayatKurikulum')) {
            $activeHistoryId = $resolvedMahasiswa->riwayatKurikulum
                ->firstWhere('is_active', true)?->id_kurikulum;

            if (filled($activeHistoryId)) {
                return $activeHistoryId;
            }
        }

        $activeHistoryId = $resolvedMahasiswa->riwayatKurikulumAktif()->value('id_kurikulum');
        if (filled($activeHistoryId)) {
            return $activeHistoryId;
        }

        return $this->resolveMatchingKurikulumId(
            $resolvedMahasiswa->id_prodi,
            $resolvedMahasiswa->angkatan,
            $resolvedMahasiswa->tanggal_masuk
        );
    }

    public function resolveKrsKurikulumId(Mahasiswa|string|null $mahasiswa): ?string
    {
        $resolvedMahasiswa = $this->resolveMahasiswa($mahasiswa);
        if (!$resolvedMahasiswa) {
            return null;
        }

        $indukId = $this->resolveMahasiswaKurikulumIndukId($resolvedMahasiswa);
        if (!$indukId) {
            return $this->resolveMahasiswaKurikulumId($resolvedMahasiswa);
        }

        $semesterAktif = Semester::query()
            ->with('tahunAkademik')
            ->where('status', 'Aktif')
            ->first();

        if (!$semesterAktif) {
            return $this->resolveMahasiswaKurikulumId($resolvedMahasiswa);
        }

        $directMatch = Kurikulum::query()
            ->where('id_kurikulum_induk', $indukId)
            ->where('id_prodi', $resolvedMahasiswa->id_prodi)
            ->where('id_semester', $semesterAktif->id)
            ->orderBy('nama_struktur_mk')
            ->orderBy('id')
            ->first();

        if ($directMatch) {
            return $directMatch->id;
        }

        $tahunAkademikAktif = $semesterAktif->tahunAkademik?->tahun_akademik;
        $jenisSemesterAktif = $this->normalizeSemesterType($semesterAktif->nama_semester);

        $matchedByAcademicYearAndType = Kurikulum::query()
            ->with('semesterMulai.tahunAkademik')
            ->where('id_kurikulum_induk', $indukId)
            ->where('id_prodi', $resolvedMahasiswa->id_prodi)
            ->whereHas('semesterMulai', function ($query) use ($tahunAkademikAktif, $jenisSemesterAktif) {
                $query->where('nama_semester', 'like', '%' . $jenisSemesterAktif . '%');

                if (filled($tahunAkademikAktif)) {
                    $query->whereHas('tahunAkademik', function ($tahunAkademikQuery) use ($tahunAkademikAktif) {
                        $tahunAkademikQuery->where('tahun_akademik', $tahunAkademikAktif);
                    });
                }
            })
            ->orderBy('nama_struktur_mk')
            ->orderBy('id')
            ->first();

        return $matchedByAcademicYearAndType?->id ?? $this->resolveMahasiswaKurikulumId($resolvedMahasiswa);
    }

    public function resolveRequestedOrMatchingKurikulumId(
        ?string $requestedKurikulumId,
        ?string $prodiId,
        $angkatan = null,
        $tanggalMasuk = null
    ): ?string {
        if (!$prodiId) {
            return null;
        }

        if (filled($requestedKurikulumId)) {
            $kurikulum = Kurikulum::query()
                ->where('id', $requestedKurikulumId)
                ->where('id_prodi', $prodiId)
                ->first();

            if (!$kurikulum) {
                throw ValidationException::withMessages([
                    'id_kurikulum' => ['Kurikulum yang dipilih tidak sesuai dengan program studi mahasiswa.'],
                ]);
            }

            return $kurikulum->id;
        }

        return $this->resolveMatchingKurikulumId($prodiId, $angkatan, $tanggalMasuk);
    }

    public function resolveMahasiswaKurikulumIndukId(Mahasiswa|string|null $mahasiswa): ?string
    {
        $resolvedMahasiswa = $this->resolveMahasiswa($mahasiswa);
        if (!$resolvedMahasiswa) {
            return null;
        }

        if ($resolvedMahasiswa->relationLoaded('riwayatKurikulumAktif')) {
            $activeIndukId = $resolvedMahasiswa->riwayatKurikulumAktif?->id_kurikulum_induk;
            if (filled($activeIndukId)) {
                return $activeIndukId;
            }
        }

        if ($resolvedMahasiswa->relationLoaded('riwayatKurikulum')) {
            $activeIndukId = $resolvedMahasiswa->riwayatKurikulum
                ->firstWhere('is_active', true)?->id_kurikulum_induk;

            if (filled($activeIndukId)) {
                return $activeIndukId;
            }
        }

        $activeIndukId = $resolvedMahasiswa->riwayatKurikulumAktif()->value('id_kurikulum_induk');
        if (filled($activeIndukId)) {
            return $activeIndukId;
        }

        $operationalId = $this->resolveMahasiswaKurikulumId($resolvedMahasiswa);
        if (!$operationalId) {
            return null;
        }

        return Kurikulum::query()
            ->where('id', $operationalId)
            ->value('id_kurikulum_induk');
    }

    public function resolveOperationalToIndukId(?string $operationalKurikulumId): ?string
    {
        if (!filled($operationalKurikulumId)) {
            return null;
        }

        return Kurikulum::query()
            ->where('id', $operationalKurikulumId)
            ->value('id_kurikulum_induk');
    }

    public function resolveOrCreateIndukFromOperational(Kurikulum $operationalKurikulum): KurikulumInduk
    {
        if ($operationalKurikulum->relationLoaded('kurikulumInduk') && $operationalKurikulum->kurikulumInduk) {
            return $operationalKurikulum->kurikulumInduk;
        }

        if (filled($operationalKurikulum->id_kurikulum_induk)) {
            $existing = KurikulumInduk::find($operationalKurikulum->id_kurikulum_induk);
            if ($existing) {
                return $existing;
            }
        }

        $defaults = $this->buildIndukDefaults($operationalKurikulum);

        $induk = KurikulumInduk::query()->firstOrCreate(
            [
                'id_prodi' => $operationalKurikulum->id_prodi,
                'id_jenis_kurikulum' => $defaults['id_jenis_kurikulum'],
                'tahun_kurikulum' => $defaults['tahun_kurikulum'],
            ],
            $defaults
        );

        $dirty = false;

        if (blank($induk->kode_kurikulum) && !empty($defaults['kode_kurikulum'])) {
            $induk->kode_kurikulum = $defaults['kode_kurikulum'];
            $dirty = true;
        }

        if (blank($induk->nama_kurikulum) && !empty($defaults['nama_kurikulum'])) {
            $induk->nama_kurikulum = $defaults['nama_kurikulum'];
            $dirty = true;
        }

        if ($induk->is_aktif === null && array_key_exists('is_aktif', $defaults)) {
            $induk->is_aktif = $defaults['is_aktif'];
            $dirty = true;
        }

        if ($dirty) {
            $induk->save();
        }

        if ($operationalKurikulum->id_kurikulum_induk !== $induk->id) {
            $operationalKurikulum->update([
                'id_kurikulum_induk' => $induk->id,
            ]);
        }

        return $induk;
    }

    public function resolveMatchingKurikulumId(?string $prodiId, $angkatan = null, $tanggalMasuk = null): ?string
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

        $sortedKurikulums = $kurikulums
            ->sortByDesc(fn(Kurikulum $kurikulum) => $this->buildKurikulumSortKey($kurikulum) ?? 0)
            ->values();

        $preferredSemesterOrder = $this->resolvePreferredSemesterOrder($angkatan, $tanggalMasuk);

        if ($cohortSortKey !== null) {
            $eligibleKurikulums = $sortedKurikulums
                ->filter(function (Kurikulum $kurikulum) use ($cohortSortKey) {
                    $kurikulumSortKey = $this->buildKurikulumSortKey($kurikulum);

                    return $kurikulumSortKey !== null && $kurikulumSortKey <= $cohortSortKey;
                })
                ->values();

            if ($eligibleKurikulums->isNotEmpty()) {
                return $this->resolvePreferredKurikulumCandidate($eligibleKurikulums, $preferredSemesterOrder)?->id;
            }
        }

        return $this->resolvePreferredKurikulumCandidate($sortedKurikulums, $preferredSemesterOrder)?->id;
    }

    private function resolveMahasiswa(Mahasiswa|string|null $mahasiswa): ?Mahasiswa
    {
        if ($mahasiswa instanceof Mahasiswa) {
            return $mahasiswa;
        }

        if (!filled($mahasiswa)) {
            return null;
        }

        return Mahasiswa::with(['riwayatKurikulum', 'riwayatKurikulumAktif'])->find($mahasiswa);
    }

    private function resolveCohortSortKey($angkatan = null, $tanggalMasuk = null): ?int
    {
        $tanggal = $this->normalizeDate($tanggalMasuk);
        if ($tanggal) {
            $year = (int) $tanggal->format('Y');
            $month = (int) $tanggal->format('n');
            $semesterOrder = $month >= 7 ? 1 : 2;
            $academicStartYear = $semesterOrder === 1 ? $year : $year - 1;

            return ($academicStartYear * 10) + $semesterOrder;
        }

        if (filled($angkatan)) {
            return ((int) $angkatan * 10) + 1;
        }

        return null;
    }

    private function resolvePreferredSemesterOrder($angkatan = null, $tanggalMasuk = null): ?int
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

    private function resolvePreferredKurikulumCandidate(Collection $kurikulums, ?int $preferredSemesterOrder): ?Kurikulum
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

    private function normalizeDate($value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return now()->setDate(
                (int) $value->format('Y'),
                (int) $value->format('m'),
                (int) $value->format('d')
            )->setTime(
                (int) $value->format('H'),
                (int) $value->format('i'),
                (int) $value->format('s')
            );
        }

        if (blank($value)) {
            return null;
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return null;
        }

        return now()->setTimestamp($timestamp);
    }

    private function normalizeSemesterType(?string $namaSemester): string
    {
        $normalized = strtolower(trim((string) $namaSemester));

        return match (true) {
            str_contains($normalized, 'ganjil') => 'ganjil',
            str_contains($normalized, 'genap') => 'genap',
            default => $normalized,
        };
    }

    private function normalizeIndukName(string $name): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($name)) ?? trim($name);

        $patterns = [
            '/\s*-\s*\d{4}\/\d{4}\s+(ganjil|genap)$/i',
            '/\s*-\s*\d{4}\s*-\s*\d{4}\s+(ganjil|genap)$/i',
            '/\s*-\s*\d{4}\s+(ganjil|genap)$/i',
        ];

        foreach ($patterns as $pattern) {
            $candidate = preg_replace($pattern, '', $normalized);
            if (is_string($candidate) && trim($candidate) !== '') {
                $normalized = trim($candidate);
                break;
            }
        }

        return $normalized;
    }

    private function buildIndukDefaults(Kurikulum $operationalKurikulum): array
    {
        $defaultJenis = RefJenisKurikulum::query()
            ->where('kode_jenis', 'INST')
            ->first();

        $tahunKurikulum = $this->resolveOperationalCurriculumYear($operationalKurikulum);
        $kodeProdi = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $operationalKurikulum->prodi?->kode_prodi) ?? '');
        $kodeJenis = $defaultJenis?->kode_jenis ?? 'INST';

        return [
            'id_jenis_kurikulum' => $defaultJenis?->id,
            'tahun_kurikulum' => $tahunKurikulum,
            'kode_kurikulum' => trim($tahunKurikulum . '-' . $kodeJenis . '-' . ($kodeProdi !== '' ? $kodeProdi : 'PRODI')),
            'nama_kurikulum' => $this->composeFormalIndukName($tahunKurikulum, $defaultJenis?->nama_jenis_kurikulum),
            'is_aktif' => false,
        ];
    }

    private function composeFormalIndukName(string $tahunKurikulum, ?string $namaJenisKurikulum): string
    {
        $suffix = filled($namaJenisKurikulum) ? ' - ' . trim((string) $namaJenisKurikulum) : '';

        return trim($tahunKurikulum . $suffix);
    }

    private function resolveOperationalCurriculumYear(Kurikulum $operationalKurikulum): string
    {
        $tahunAkademik = $operationalKurikulum->semesterMulai?->tahunAkademik?->tahun_akademik;
        if (filled($tahunAkademik)) {
            return substr((string) $tahunAkademik, 0, 4);
        }

        if (preg_match('/(20\d{2})/', (string) $operationalKurikulum->nama_struktur_mk, $matches) === 1) {
            return $matches[1];
        }

        return now()->format('Y');
    }
}
