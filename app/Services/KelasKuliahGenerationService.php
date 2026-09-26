<?php

namespace App\Services;

use App\Models\MasterData\KelasKuliah;
use App\Models\MasterData\KurikulumMataKuliah;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KelasKuliahGenerationService
{
    /**
     * Daftar mata kuliah calon kelas dari kurikulum + semester_ke tertentu.
     */
    public function candidateMataKuliah(array $filters): Collection
    {
        return KurikulumMataKuliah::query()
            ->with([
                'mataKuliah:id,kode_mk,nama_mk,sks',
                'kurikulum:id,id_prodi',
            ])
            ->where('id_kurikulum', $filters['id_kurikulum'])
            ->where('semester_ke', $filters['semester_ke'])
            ->orderByDesc('is_wajib')
            ->orderBy('id_mata_kuliah')
            ->get();
    }

    /**
     * id_kurikulum_mata_kuliah yang sudah punya kelas pada semester target.
     * $kmkIds: Collection|array berisi id kurikulum_mata_kuliah (atau item yang memiliki atribut 'id').
     */
    public function detectDuplicatedKmkIds(array $filters, Collection|array $kmkIds): array
    {
        $ids = collect($kmkIds);

        // Item bisa berupa model (pluck 'id') atau array/koleksi id mentah.
        $first = $ids->first();
        $values = is_object($first) && isset($first->id) ? $ids->pluck('id') : $ids;

        if ($values->isEmpty()) {
            return [];
        }

        return KelasKuliah::query()
            ->where('id_semester', $filters['id_semester'])
            ->whereIn('id_kurikulum_mata_kuliah', $values)
            ->pluck('id_kurikulum_mata_kuliah')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Daftar baris kandidat kelas beserta status (will_create/duplicate).
     * Dipakai untuk mengisi tabel "Lihat MK" di halaman generate.
     */
    public function candidates(array $filters): array
    {
        $candidates = $this->candidateMataKuliah($filters);

        $defaultNama = isset($filters['default_nama_kelas']) && trim($filters['default_nama_kelas']) !== ''
            ? trim($filters['default_nama_kelas'])
            : null;

        $existingClasses = KelasKuliah::query()
            ->where('id_semester', $filters['id_semester'])
            ->whereIn('id_kurikulum_mata_kuliah', $candidates->pluck('id'))
            ->get(['id_kurikulum_mata_kuliah', 'nama_kelas']);

        $existingGrouped = $existingClasses->groupBy('id_kurikulum_mata_kuliah');

        $rows = $candidates->map(function (KurikulumMataKuliah $kmk) use ($existingGrouped, $defaultNama, $filters) {
            $existing = $existingGrouped->get($kmk->id, collect());
            $existingNames = $existing->pluck('nama_kelas')->filter()->values()->all();

            $isExactDuplicate = $defaultNama !== null && in_array($defaultNama, $existingNames, true);

            return [
                'id_kurikulum_mata_kuliah' => $kmk->id,
                'kode_mk' => $kmk->mataKuliah?->kode_mk,
                'nama_mk' => $kmk->mataKuliah?->nama_mk,
                'sks' => (int) ($kmk->mataKuliah?->sks ?? 0),
                'semester_ke' => (int) $kmk->semester_ke,
                'nama_kelas' => $defaultNama,
                'existing_kelas' => $existingNames,
                'status' => $isExactDuplicate ? 'duplicate' : 'will_create',
                'message' => $isExactDuplicate
                    ? 'Kelas "'.$defaultNama.'" sudah ada pada semester target.'
                    : (! empty($existingNames) ? 'Siap dibuat (Sudah ada kelas: '.implode(', ', $existingNames).')' : 'Siap dibuat.'),
                'default_kapasitas' => $filters['default_kapasitas'] ?? null,
            ];
        })->values();

        return [
            'data' => $rows,
            'summary' => [
                'total_items' => $rows->count(),
                'will_create_count' => $rows->where('status', 'will_create')->count(),
                'duplicate_count' => $rows->where('status', 'duplicate')->count(),
            ],
        ];
    }

    /**
     * Buat kelas kuliah untuk daftar id_kurikulum_mata_kuliah (yaitu MK) yang dipilih.
     * Satu request = satu DB::transaction; kelas dengan nama yang sama persis di-skip.
     */
    public function create(array $validated, string $createdBy): array
    {
        $filters = [
            'id_prodi' => $validated['id_prodi'],
            'id_kurikulum' => $validated['id_kurikulum'],
            'id_semester' => $validated['id_semester'],
            'semester_ke' => $validated['semester_ke'],
        ];

        // Pastikan BMK yang dikirim benar milik kurikulum+semester_ke target.
        $allowedKmkIds = $this->candidateMataKuliah($filters)->pluck('id')->all();

        $rows = collect($validated['rows'] ?? [])
            ->filter(fn (array $row) => in_array($row['id_kurikulum_mata_kuliah'], $allowedKmkIds, true))
            ->values();

        if ($rows->isEmpty()) {
            return $this->emptyReport('Tidak ada mata kuliah valid untuk dibuat kelasnya.');
        }

        $results = [];

        try {
            DB::beginTransaction();

            foreach ($rows as $row) {
                $kmkId = $row['id_kurikulum_mata_kuliah'];
                $namaKelas = trim($row['nama_kelas']);

                // Validasi duplikasi hanya jika kelas dengan nama yang sama persis sudah ada pada semester target
                $alreadyExists = KelasKuliah::query()
                    ->where('id_semester', $validated['id_semester'])
                    ->where('id_kurikulum_mata_kuliah', $kmkId)
                    ->where('nama_kelas', $namaKelas)
                    ->exists();

                if ($alreadyExists) {
                    $results[] = [
                        'id_kurikulum_mata_kuliah' => $kmkId,
                        'nama_kelas' => $namaKelas,
                        'status' => 'skipped',
                        'message' => 'Kelas "'.$namaKelas.'" sudah ada pada semester target.',
                    ];

                    continue;
                }

                try {
                    $kelas = KelasKuliah::create([
                        'id_prodi' => $validated['id_prodi'],
                        'id_kurikulum_mata_kuliah' => $kmkId,
                        'id_semester' => $validated['id_semester'],
                        'nama_kelas' => $namaKelas,
                        'kapasitas_peserta' => $row['kapasitas_peserta'] ?? null,
                    ]);

                    $results[] = [
                        'id_kelas_kuliah' => $kelas->id,
                        'id_kurikulum_mata_kuliah' => $kmkId,
                        'nama_kelas' => $kelas->nama_kelas,
                        'status' => 'created',
                        'message' => 'Kelas berhasil dibuat.',
                    ];
                } catch (\Throwable $e) {
                    $results[] = [
                        'id_kurikulum_mata_kuliah' => $kmkId,
                        'nama_kelas' => $namaKelas,
                        'status' => 'failed',
                        'message' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        $collection = collect($results);

        return [
            'summary' => [
                'total' => $collection->count(),
                'created_count' => $collection->where('status', 'created')->count(),
                'skipped_count' => $collection->where('status', 'skipped')->count(),
                'failed_count' => $collection->where('status', 'failed')->count(),
            ],
            'results' => $results,
        ];
    }

    private function emptyReport(string $message): array
    {
        return [
            'summary' => [
                'total' => 0,
                'created_count' => 0,
                'skipped_count' => 0,
                'failed_count' => 0,
            ],
            'results' => [],
            'message' => $message,
        ];
    }
}
