<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use Exception;
use App\Models\Akademik\KRS;
use App\Models\User;
use App\Models\MasterData\Prodi;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\Kurikulum;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\MasterData\Mahasiswa;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Exports\MahasiswaExport;
use App\Imports\MahasiswaImport;
use App\Services\ActiveCurriculumService;
use App\Services\MahasiswaCurriculumContextService;
use App\Services\StudentAngkatanResolverService;
use Maatwebsite\Excel\Facades\Excel;

class MahasiswaController extends Controller
{
    public function __construct(
        private readonly MahasiswaCurriculumContextService $mahasiswaCurriculumContextService,
        private readonly ActiveCurriculumService $activeCurriculumService,
        private readonly StudentAngkatanResolverService $studentAngkatanResolverService
    ) {}

    public function index(): JsonResponse
    {
        try {
            // Memuat relasi yang relevan termasuk user
            $mahasiswas = Mahasiswa::with(['prodi', 'dosenWali', 'user'])
                ->where('status', '!=', 'PMB')
                ->get()
                ->map(fn(Mahasiswa $mahasiswa) => $this->serializeMahasiswa($mahasiswa));
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
                'dosenWali',
                'user',
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
                'data' => $this->serializeMahasiswa($mahasiswa)
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
                'nim' => 'required|string|max:20|unique:mahasiswa,nim',
                'nik' => 'nullable|string|max:20',
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
                $resolvedAngkatan = $this->resolveSubmittedAngkatan($request);

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
                $mahasiswaData['angkatan'] = $resolvedAngkatan;
                $mahasiswaData['user_id'] = $user->id;

                $mahasiswa = Mahasiswa::create($mahasiswaData);

                return [
                    'user' => $user,
                    'mahasiswa' => $mahasiswa->fresh(['prodi', 'dosenWali', 'user'])
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Mahasiswa dan User berhasil dibuat.',
                'data' => [
                    'mahasiswa' => $this->serializeMahasiswa($result['mahasiswa']),
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
                'nim' => 'sometimes|string|max:20|unique:mahasiswa,nim,' . $id,
                'nik' => 'sometimes|string|max:20',
                'nama_mahasiswa' => 'sometimes|string|max:255',
                'jenis_kelamin' => 'sometimes|in:L,P',
                'tanggal_lahir' => 'nullable|date',
                'tempat_lahir' => 'nullable|string|max:255',
                'tanggal_masuk' => 'nullable|date',
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
                $targetAngkatan = $this->resolveSubmittedAngkatan($request, $mahasiswa->angkatan, $mahasiswa->nim);

                $isChangingProdi = (string) $mahasiswa->id_prodi !== (string) $targetProdiId;
                $isChangingAngkatan = (string) ($mahasiswa->angkatan ?? '') !== (string) ($targetAngkatan ?? '');

                // Mahasiswa tidak terikat ke kurikulum; prodi/angkatan hanya
                // memengaruhi konteks kurikulum (di-resolve saat dipakai), bukan
                // histori penugasan. Guard akademik untuk perubahan prodi tetap relevan.
                if (($isChangingProdi || $isChangingAngkatan) && $this->hasAcademicHistory($mahasiswa)) {
                    $messages = [];

                    if ($isChangingProdi) {
                        $messages['id_prodi'] = ['Program studi mahasiswa tidak dapat diubah karena histori akademik sudah berjalan.'];
                    }

                    if ($isChangingAngkatan) {
                        $messages['angkatan'] = ['Angkatan mahasiswa tidak dapat diubah karena histori akademik sudah berjalan.'];
                    }

                    throw ValidationException::withMessages($messages);
                }

                $mahasiswaData['angkatan'] = $targetAngkatan;

                $mahasiswa->update($mahasiswaData);

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
                    'mahasiswa' => $mahasiswa->fresh(['prodi', 'dosenWali', 'user'])
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Mahasiswa dan User berhasil diperbarui.',
                'data' => $this->serializeMahasiswa($result['mahasiswa'])
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
                $user = $mahasiswa->user;

                $this->purgeMahasiswaRelatedData($mahasiswa);

                // 1. Hapus Mahasiswa terlebih dahulu (karena memiliki foreign key ke User)
                $mahasiswa->delete();

                // 2. Hapus User terkait jika ada
                if ($user) {
                    if (method_exists($user, 'syncRoles')) {
                        $user->syncRoles([]);
                    }

                    $user->delete();
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Mahasiswa, user, dan data akademik terkait berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'uuid|exists:mahasiswa,id',
            ]);

            $mahasiswas = Mahasiswa::with('user')
                ->whereIn('id', $validated['ids'])
                ->get();

            if ($mahasiswas->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada mahasiswa yang ditemukan untuk dihapus.',
                ], 404);
            }

            DB::transaction(function () use ($mahasiswas) {
                foreach ($mahasiswas as $mahasiswa) {
                    $user = $mahasiswa->user;

                    $this->purgeMahasiswaRelatedData($mahasiswa);

                    $mahasiswa->delete();

                    if ($user) {
                        if (method_exists($user, 'syncRoles')) {
                            $user->syncRoles([]);
                        }

                        $user->delete();
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => sprintf('%d mahasiswa beserta user dan data akademik terkait berhasil dihapus.', $mahasiswas->count()),
                'data' => [
                    'deleted_ids' => $mahasiswas->pluck('id')->values(),
                    'deleted_count' => $mahasiswas->count(),
                ],
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
                'message' => 'Terjadi kesalahan saat menghapus mahasiswa secara kolektif.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function purgeMahasiswaRelatedData(Mahasiswa $mahasiswa): void
    {
        $mahasiswaId = $mahasiswa->id;

        $krsIds = DB::table('krs')
            ->where('id_mahasiswa', $mahasiswaId)
            ->pluck('id');

        if ($krsIds->isNotEmpty()) {
            DB::table('krs_detail')
                ->whereIn('id_krs', $krsIds)
                ->delete();

            DB::table('krs')
                ->whereIn('id', $krsIds)
                ->delete();
        }

        $khsIds = DB::table('khs')
            ->where('id_mahasiswa', $mahasiswaId)
            ->pluck('id');

        if ($khsIds->isNotEmpty()) {
            DB::table('khs_revisions')
                ->whereIn('id_khs', $khsIds)
                ->delete();

            DB::table('khs_detail')
                ->whereIn('id_khs', $khsIds)
                ->delete();

            DB::table('khs')
                ->whereIn('id', $khsIds)
                ->delete();
        }

        $transkripIds = DB::table('transkrip')
            ->where('id_mahasiswa', $mahasiswaId)
            ->pluck('id');

        if ($transkripIds->isNotEmpty()) {
            DB::table('transkrip_detail')
                ->whereIn('id_transkrip', $transkripIds)
                ->delete();

            DB::table('transkrip')
                ->whereIn('id', $transkripIds)
                ->delete();
        }

        $tugasAkhirIds = DB::table('tugas_akhir')
            ->where('id_mahasiswa', $mahasiswaId)
            ->pluck('id');

        if ($tugasAkhirIds->isNotEmpty()) {
            DB::table('tugas_akhir_pembimbing')
                ->whereIn('id_tugas_akhir', $tugasAkhirIds)
                ->delete();

            DB::table('tugas_akhir_ujian')
                ->whereIn('id_tugas_akhir', $tugasAkhirIds)
                ->delete();

            DB::table('tugas_akhir')
                ->whereIn('id', $tugasAkhirIds)
                ->delete();
        }

        DB::table('peserta_wisuda')
            ->where('id_mahasiswa', $mahasiswaId)
            ->delete();

        DB::table('kelulusan')
            ->where('id_mahasiswa', $mahasiswaId)
            ->delete();

        DB::table('yudisium')
            ->where('id_mahasiswa', $mahasiswaId)
            ->delete();

        DB::table('krs_collective_batch_items')
            ->where('id_mahasiswa', $mahasiswaId)
            ->delete();
        if (filled($mahasiswa->user_id)) {
            DB::table('refresh_tokens')
                ->where('user_id', $mahasiswa->user_id)
                ->delete();
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

    private function hasAcademicHistory(Mahasiswa $mahasiswa): bool
    {
        return KRS::query()
            ->where('id_mahasiswa', $mahasiswa->id)
            ->exists();
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

    private function resolveSubmittedAngkatan(Request $request, $fallbackAngkatan = null, ?string $fallbackNim = null): ?int
    {
        $angkatanInput = $request->has('angkatan')
            ? $request->input('angkatan')
            : $fallbackAngkatan;

        $nimInput = $request->input('nim', $fallbackNim);

        return $this->studentAngkatanResolverService->resolve(
            filled($angkatanInput) ? (string) $angkatanInput : null,
            filled($nimInput) ? (string) $nimInput : null
        );
    }

    private function serializeMahasiswa(Mahasiswa $mahasiswa): array
    {
        $mahasiswa->loadMissing([
            'prodi.kaprodi',
            'dosenWali',
            'user',
        ]);

        $curriculumContext = $this->activeCurriculumService->resolveCurriculumContext($mahasiswa);
        $activeKurikulum = $this->activeCurriculumService->resolveActiveKurikulum($mahasiswa);

        return [
            ...$mahasiswa->toArray(),
            'id_kurikulum' => $curriculumContext['id_kurikulum_operasional'] ?? null,
            'kurikulum' => $activeKurikulum?->toArray(),
            'kurikulum_context' => $curriculumContext,
        ];
    }
}
