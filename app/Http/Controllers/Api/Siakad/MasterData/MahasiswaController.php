<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use Exception;
use App\Models\Akademik\KRS;
use App\Models\User;
use App\Models\MasterData\Prodi;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\Kurikulum;
use App\Models\MasterData\RiwayatKurikulumMahasiswa;
use App\Models\MasterData\Semester;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\MasterData\Mahasiswa;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Exports\MahasiswaExport;
use App\Imports\MahasiswaImport;
use Maatwebsite\Excel\Facades\Excel;

class MahasiswaController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            // Memuat relasi yang relevan termasuk user
            $mahasiswas = Mahasiswa::with(['prodi', 'kurikulum', 'dosenWali', 'user', 'riwayatKurikulum.kurikulum'])
                ->where('status', '!=', 'PMB')
                ->get();
            $dataprodi = Prodi::all();
            $datadosen = Dosen::all();
            $datakurikulum = Kurikulum::with(['prodi', 'semesterMulai.tahunAkademik'])->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar Mahasiswa',
                'data' => [
                    'mahasiswa'     => $mahasiswas,
                    'prodi'         => $dataprodi,
                    'dosen'         => $datadosen,
                    'kurikulum'     => $datakurikulum,
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data mahasiswa.',
                'error' => $e->getMessage() // Hanya tampilkan pesan error jika debug=true
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $mahasiswa = Mahasiswa::with([
                'prodi',
                'kurikulum',
                'dosenWali',
                'user',
                'riwayatKurikulum.kurikulum.semesterMulai.tahunAkademik',
            ])->find($id);

            if (!$mahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Mahasiswa',
                'data' => $mahasiswa
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_prodi' => 'required|exists:prodi,id',
                'id_kurikulum' => 'nullable|exists:kurikulum,id',
                'nim' => 'required|string|max:20|unique:mahasiswa,nim',
                'nik' => 'nullable|string|max:20|unique:mahasiswa,nik',
                'nama_mahasiswa' => 'required|string|max:255',
                'jenis_kelamin' => 'nullable|in:L,P',
                'tempat_lahir' => 'nullable|string|max:255',
                'tanggal_lahir' => 'nullable|date',
                'tanggal_masuk' => 'nullable|date',
                'alamat' => 'nullable|string',
                'agama' => 'nullable|in:Islam,Kristen,Katolik,Hindu,Buddha,Konghucu',
                'status' => 'nullable|in:Aktif,Cuti,DO,Lulus',
                'angkatan' => 'nullable|integer|min:1900|max:' . (date('Y') + 10),
                'email' => 'nullable|email|unique:users,email',
                'password' => 'nullable|min:6'
            ]);

            // Gunakan transaksi untuk memastikan kedua data tersimpan atau gagal bersama
            $result = DB::transaction(function () use ($request) {
                $resolvedKurikulumId = $this->resolveKurikulumId(
                    $request->input('id_kurikulum'),
                    $request->input('id_prodi'),
                    $request->input('angkatan'),
                    $request->input('tanggal_masuk')
                );

                if (!$resolvedKurikulumId) {
                    throw ValidationException::withMessages([
                        'id_kurikulum' => ['Kurikulum untuk program studi ini belum tersedia. Pilih atau buat kurikulum terlebih dahulu.'],
                    ]);
                }

                // 1. Buat User terlebih dahulu
                $password = $request->filled('password')
                    ? Hash::make($request->password)
                    : Hash::make($request->tanggal_lahir ? date('dmY', strtotime($request->tanggal_lahir)) : 'password');

                $user = User::create([
                    'name' => $request->nama_mahasiswa,
                    'email' => $request->email,
                    'password' => $password,
                    'status' => $request->status === 'Aktif' ? 'aktif' : 'tidak-aktif'
                ]);

                // 2. Assign role "mahasiswa" ke user
                $user->assignRole('mahasiswa');

                // 3. Buat Mahasiswa dengan menghubungkan ke user yang baru dibuat
                $mahasiswaData = $this->buildMahasiswaPayload($request);
                $mahasiswaData['user_id'] = $user->id;
                $mahasiswaData['id_kurikulum'] = $resolvedKurikulumId;

                $mahasiswa = Mahasiswa::create($mahasiswaData);
                $this->syncActiveKurikulumHistory(
                    $mahasiswa,
                    $resolvedKurikulumId,
                    $request->input('tanggal_masuk'),
                    'Kurikulum awal mahasiswa'
                );

                return [
                    'user' => $user,
                    'mahasiswa' => $mahasiswa
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Mahasiswa dan User berhasil dibuat.',
                'data' => [
                    'mahasiswa' => $result['mahasiswa'],
                    'user' => $result['user']
                ]
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $mahasiswa = Mahasiswa::with('user')->find($id);

            if (!$mahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa tidak ditemukan.'
                ], 404);
            }

            $request->validate([
                'id_prodi' => 'sometimes|exists:prodi,id',
                'id_kurikulum' => 'nullable|exists:kurikulum,id',
                'nim' => 'sometimes|string|max:20|unique:mahasiswa,nim,' . $id,
                'nik' => 'sometimes|string|max:20|unique:mahasiswa,nik,' . $id,
                'nama_mahasiswa' => 'sometimes|string|max:255',
                'jenis_kelamin' => 'sometimes|in:L,P',
                'tanggal_lahir' => 'sometimes|date',
                'tempat_lahir' => 'sometimes|string|max:255',
                'tanggal_masuk' => 'sometimes|date',
                'alamat' => 'nullable|string',
                'agama' => 'sometimes|in:Islam,Kristen,Katolik,Hindu,Buddha,Konghucu',
                'status' => 'sometimes|in:Aktif,Cuti,DO,Lulus',
                'angkatan' => 'nullable|integer|min:1900|max:' . (date('Y') + 10),
                'email' => 'nullable|email|unique:users,email,' . $mahasiswa->user_id,
                'password' => 'nullable|string|min:6'
            ]);

            // Gunakan transaksi untuk memastikan kedua data terupdate atau gagal bersama
            $result = DB::transaction(function () use ($request, $mahasiswa) {
                // 1. Update Mahasiswa
                $mahasiswaData = $this->buildMahasiswaPayload($request);

                $targetProdiId = $request->input('id_prodi', $mahasiswa->id_prodi);
                if (
                    $request->has('id_kurikulum')
                    || $request->has('id_prodi')
                    || $request->has('angkatan')
                    || $request->has('tanggal_masuk')
                ) {
                    $resolvedKurikulumId = $this->resolveKurikulumId(
                        $request->input('id_kurikulum'),
                        $targetProdiId,
                        $request->input('angkatan', $mahasiswa->angkatan),
                        $request->input('tanggal_masuk', $mahasiswa->tanggal_masuk)
                    );

                    if (!$resolvedKurikulumId) {
                        throw ValidationException::withMessages([
                            'id_kurikulum' => ['Kurikulum untuk program studi ini belum tersedia. Pilih atau buat kurikulum terlebih dahulu.'],
                        ]);
                    }

                    $isChangingKurikulum = $mahasiswa->id_kurikulum !== $resolvedKurikulumId;
                    $isChangingProdi = $mahasiswa->id_prodi !== $targetProdiId;

                    if (($isChangingKurikulum || $isChangingProdi) && $this->hasAcademicHistory($mahasiswa)) {
                        throw ValidationException::withMessages([
                            'id_kurikulum' => ['Kurikulum atau program studi mahasiswa tidak dapat diubah karena histori akademik sudah berjalan.'],
                        ]);
                    }

                    $mahasiswaData['id_kurikulum'] = $resolvedKurikulumId;
                }

                $mahasiswa->update($mahasiswaData);
                $this->syncActiveKurikulumHistory(
                    $mahasiswa->fresh(),
                    $mahasiswaData['id_kurikulum'] ?? $mahasiswa->id_kurikulum,
                    $mahasiswaData['tanggal_masuk'] ?? $mahasiswa->tanggal_masuk,
                    'Sinkronisasi kurikulum aktif mahasiswa'
                );

                // 2. Update User jika ada perubahan
                if ($mahasiswa->user) {
                    $userData = [];

                    if ($request->has('nama_mahasiswa')) {
                        $userData['name'] = $request->nama_mahasiswa;
                    }

                    if ($request->has('email')) {
                        $userData['email'] = $request->email;
                    }

                    // Hanya update password jika password diisi
                    if ($request->filled('password')) {
                        $userData['password'] = Hash::make($request->password);
                    }

                    // Sinkronisasi status: jika status mahasiswa berubah, update status user
                    if ($request->has('status')) {
                        $userData['status'] = $request->status === 'Aktif' ? 'aktif' : 'tidak-aktif';
                    }

                    if (!empty($userData)) {
                        $mahasiswa->user->update($userData);
                    }
                }

                return [
                    'mahasiswa' => $mahasiswa->fresh(['user'])
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Mahasiswa dan User berhasil diperbarui.',
                'data' => $result['mahasiswa']
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $mahasiswa = Mahasiswa::with('user')->find($id);

            if (!$mahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa tidak ditemukan.'
                ], 404);
            }

            // Gunakan transaksi untuk memastikan kedua data terhapus atau gagal bersama
            DB::transaction(function () use ($mahasiswa) {
                // 1. Hapus Mahasiswa terlebih dahulu (karena memiliki foreign key ke User)
                $mahasiswa->delete();

                // 2. Hapus User terkait jika ada
                if ($mahasiswa->user) {
                    $mahasiswa->user->delete();
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Mahasiswa dan User berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function export(Request $request)
    {
        try {
            $id_prodi = $request->get('id_prodi');
            $is_dummy = $request->get('is_dummy', false);

            $filename = 'data_mahasiswa_' . date('Y_m_d') . '.xlsx';

            return Excel::download(new MahasiswaExport($id_prodi, $is_dummy), $filename);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat export data mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function exportTemplate(Request $request, $id_prodi = null)
    {
        try {
            $filename = 'template_import_mahasiswa_' . date('Y_m_d') . '.xlsx';

            return Excel::download(new MahasiswaExport($id_prodi, true), $filename);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat download template import mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function import(Request $request, $id_prodi): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|mimes:xlsx,xls|max:10240', // Max 10MB
            ]);

            $import = new MahasiswaImport($id_prodi);
            Excel::import($import, $request->file('file'));

            $errors = $import->getErrors();
            $successCount = $import->getSuccessCount();
            $rowCount = $import->getRowCount();

            $response = [
                'success' => true,
                'message' => 'Import data mahasiswa selesai.',
                'data' => [
                    'total_rows' => $rowCount,
                    'success_count' => $successCount,
                    'error_count' => count($errors),
                    'errors' => $errors
                ]
            ];

            if (!empty($errors)) {
                $response['message'] = 'Import selesai dengan beberapa error. Lihat detail error di bawah.';
            }

            return response()->json($response, 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat import data mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function riwayatKurikulum(string $id): JsonResponse
    {
        $mahasiswa = Mahasiswa::with([
            'prodi',
            'kurikulum',
            'riwayatKurikulum.kurikulum.semesterMulai.tahunAkademik',
            'riwayatKurikulum.createdBy:id,name',
        ])->find($id);

        if (!$mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'mahasiswa' => $mahasiswa,
                'riwayat_kurikulum' => $mahasiswa->riwayatKurikulum,
            ],
        ]);
    }

    public function migrateKurikulum(Request $request, string $id): JsonResponse
    {
        $mahasiswa = Mahasiswa::with(['riwayatKurikulum'])->find($id);

        if (!$mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'id_kurikulum_tujuan' => 'required|uuid|exists:kurikulum,id',
            'tanggal_mulai' => 'nullable|date',
            'catatan' => 'nullable|string',
        ]);

        $targetKurikulum = Kurikulum::with('semesterMulai.tahunAkademik')
            ->where('id', $validated['id_kurikulum_tujuan'])
            ->where('id_prodi', $mahasiswa->id_prodi)
            ->first();

        if (!$targetKurikulum) {
            return response()->json([
                'success' => false,
                'message' => 'Kurikulum tujuan tidak sesuai dengan program studi mahasiswa.',
            ], 422);
        }

        if ($mahasiswa->id_kurikulum === $targetKurikulum->id) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa sudah menggunakan kurikulum tersebut.',
            ], 422);
        }

        if ($this->hasOpenKrsProcess($mahasiswa)) {
            return response()->json([
                'success' => false,
                'message' => 'Migrasi kurikulum tidak dapat dilakukan karena mahasiswa masih memiliki proses KRS yang belum final.',
            ], 422);
        }

        $tanggalMulai = $validated['tanggal_mulai'] ?? now()->toDateString();
        $catatan = $validated['catatan'] ?? sprintf(
            'Migrasi kurikulum dari %s ke %s',
            $mahasiswa->id_kurikulum,
            $targetKurikulum->id
        );

        $result = DB::transaction(function () use ($mahasiswa, $targetKurikulum, $tanggalMulai, $catatan, $request) {
            RiwayatKurikulumMahasiswa::query()
                ->where('id_mahasiswa', $mahasiswa->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'tanggal_selesai' => $tanggalMulai,
                    'updated_at' => now(),
                ]);

            $mahasiswa->update([
                'id_kurikulum' => $targetKurikulum->id,
            ]);

            RiwayatKurikulumMahasiswa::create([
                'id_mahasiswa' => $mahasiswa->id,
                'id_kurikulum' => $targetKurikulum->id,
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_selesai' => null,
                'is_active' => true,
                'catatan' => $catatan,
                'created_by' => $request->user()?->id,
            ]);

            return $mahasiswa->fresh([
                'prodi',
                'kurikulum',
                'riwayatKurikulum.kurikulum.semesterMulai.tahunAkademik',
                'riwayatKurikulum.createdBy:id,name',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Migrasi kurikulum mahasiswa berhasil diproses.',
            'data' => $result,
        ]);
    }

    private function resolveKurikulumId(
        ?string $requestedKurikulumId,
        string $prodiId,
        $angkatan = null,
        $tanggalMasuk = null
    ): ?string
    {
        if (filled($requestedKurikulumId)) {
            $kurikulum = Kurikulum::where('id', $requestedKurikulumId)
                ->where('id_prodi', $prodiId)
                ->first();

            if (!$kurikulum) {
                throw ValidationException::withMessages([
                    'id_kurikulum' => ['Kurikulum yang dipilih tidak sesuai dengan program studi mahasiswa.'],
                ]);
            }

            return $kurikulum->id;
        }

        $cohortSortKey = $this->resolveCohortSortKey($angkatan, $tanggalMasuk);
        $kurikulums = Kurikulum::with('semesterMulai.tahunAkademik')
            ->where('id_prodi', $prodiId)
            ->get();

        if ($kurikulums->isEmpty()) {
            return null;
        }

        $sortedKurikulums = $kurikulums->sortByDesc(function (Kurikulum $kurikulum) {
            return $this->buildKurikulumSortKey($kurikulum);
        })->values();

        $preferredSemesterOrder = $this->resolvePreferredSemesterOrder($angkatan, $tanggalMasuk);

        if ($cohortSortKey !== null) {
            $eligibleKurikulums = $sortedKurikulums->filter(function (Kurikulum $kurikulum) use ($cohortSortKey) {
                $kurikulumSortKey = $this->buildKurikulumSortKey($kurikulum);

                return $kurikulumSortKey !== null && $kurikulumSortKey <= $cohortSortKey;
            })->values();

            if ($eligibleKurikulums->isNotEmpty()) {
                $matchedKurikulum = $this->resolvePreferredKurikulumCandidate(
                    $eligibleKurikulums,
                    $preferredSemesterOrder
                );

                return $matchedKurikulum->id;
            }
        }

        return $this->resolvePreferredKurikulumCandidate(
            $sortedKurikulums,
            $preferredSemesterOrder
        )?->id;
    }

    private function hasAcademicHistory(Mahasiswa $mahasiswa): bool
    {
        return KRS::query()
            ->where('id_mahasiswa', $mahasiswa->id)
            ->exists();
    }

    private function hasOpenKrsProcess(Mahasiswa $mahasiswa): bool
    {
        return KRS::query()
            ->where('id_mahasiswa', $mahasiswa->id)
            ->where(function ($query) {
                $query->where('is_locked', false)
                    ->orWhereIn('status_approval', [
                        KRS::STATUS_PENDING,
                        KRS::STATUS_REVISED,
                    ]);
            })
            ->exists();
    }

    private function resolveCohortSortKey($angkatan = null, $tanggalMasuk = null): ?int
    {
        try {
            if (!blank($tanggalMasuk)) {
                $timestamp = strtotime((string) $tanggalMasuk);
                if ($timestamp !== false) {
                    $year = (int) date('Y', $timestamp);
                    $month = (int) date('n', $timestamp);
                    $semesterOrder = $month >= 7 ? 1 : 2;
                    $academicStartYear = $semesterOrder === 1 ? $year : $year - 1;

                    return ($academicStartYear * 10) + $semesterOrder;
                }
            }
        } catch (Exception) {
        }

        if (filled($angkatan)) {
            return ((int) $angkatan * 10) + 1;
        }

        return null;
    }

    private function extractKurikulumStartYear(Kurikulum $kurikulum): ?int
    {
        $tahunAkademik = $kurikulum->semesterMulai?->tahunAkademik?->tahun_akademik;

        if (!$tahunAkademik) {
            return null;
        }

        return (int) substr((string) $tahunAkademik, 0, 4);
    }

    private function buildKurikulumSortKey(Kurikulum $kurikulum): ?int
    {
        $tahunMulai = $this->extractKurikulumStartYear($kurikulum);
        if ($tahunMulai === null) {
            return null;
        }

        $semesterOrder = $this->resolveSemesterOrder($kurikulum->semesterMulai?->kode_semester, $kurikulum->semesterMulai?->nama_semester);

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

    private function resolvePreferredKurikulumCandidate(
        Collection $kurikulums,
        ?int $preferredSemesterOrder
    ): ?Kurikulum {
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

    private function resolvePreferredSemesterOrder($angkatan = null, $tanggalMasuk = null): ?int
    {
        $resolvedAngkatan = filled($angkatan) ? (int) $angkatan : $this->resolveAngkatanFromTanggalMasuk($tanggalMasuk);

        if ($resolvedAngkatan !== null) {
            $semesterBerjalan = $this->resolveSemesterBerjalanPadaAkademikAktif($resolvedAngkatan);

            if ($semesterBerjalan !== null) {
                return $semesterBerjalan % 2 === 1 ? 1 : 2;
            }
        }

        $cohortSortKey = $this->resolveCohortSortKey($resolvedAngkatan, $tanggalMasuk);

        return $cohortSortKey !== null ? (int) substr((string) $cohortSortKey, -1) : null;
    }

    private function resolveSemesterBerjalanPadaAkademikAktif(int $angkatan): ?int
    {
        $semesterAktif = $this->getActiveSemester();
        if (!$semesterAktif) {
            return null;
        }

        $tahunMulaiAktif = (int) substr((string) $semesterAktif->tahunAkademik?->tahun_akademik, 0, 4);
        if ($tahunMulaiAktif <= 0) {
            return null;
        }

        $semesterOrder = $this->resolveSemesterOrder(
            $semesterAktif->kode_semester,
            $semesterAktif->nama_semester
        );

        return max(1, (($tahunMulaiAktif - $angkatan) * 2) + $semesterOrder);
    }

    private function resolveAngkatanFromTanggalMasuk($tanggalMasuk = null): ?int
    {
        try {
            if (!blank($tanggalMasuk)) {
                $timestamp = strtotime((string) $tanggalMasuk);

                if ($timestamp !== false) {
                    return (int) date('Y', $timestamp);
                }
            }
        } catch (Exception) {
        }

        return null;
    }

    private function getActiveSemester(): ?Semester
    {
        return Semester::with('tahunAkademik:id,tahun_akademik,status_aktif')
            ->select('id', 'id_tahun_akademik', 'nama_semester', 'kode_semester', 'tanggal_mulai', 'tanggal_selesai', 'status')
            ->where('status', 'Aktif')
            ->first();
    }

    private function buildMahasiswaPayload(Request $request): array
    {
        $payload = $request->only([
            'id_prodi',
            'id_dosen',
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
            'angkatan',
        ]);

        return $payload;
    }

    private function syncActiveKurikulumHistory(
        Mahasiswa $mahasiswa,
        ?string $kurikulumId,
        $tanggalMulai = null,
        ?string $catatan = null
    ): void {
        if (!$kurikulumId) {
            return;
        }

        $existingActive = RiwayatKurikulumMahasiswa::query()
            ->where('id_mahasiswa', $mahasiswa->id)
            ->where('is_active', true)
            ->first();

        if ($existingActive) {
            if ($existingActive->id_kurikulum !== $kurikulumId) {
                $existingActive->update([
                    'id_kurikulum' => $kurikulumId,
                    'tanggal_mulai' => $tanggalMulai ?: $existingActive->tanggal_mulai ?: $mahasiswa->tanggal_masuk ?: now()->toDateString(),
                    'catatan' => $catatan ?: $existingActive->catatan,
                ]);
            }

            return;
        }

        RiwayatKurikulumMahasiswa::create([
            'id_mahasiswa' => $mahasiswa->id,
            'id_kurikulum' => $kurikulumId,
            'tanggal_mulai' => $tanggalMulai ?: $mahasiswa->tanggal_masuk ?: now()->toDateString(),
            'tanggal_selesai' => null,
            'is_active' => true,
            'catatan' => $catatan,
            'created_by' => null,
        ]);
    }
}
