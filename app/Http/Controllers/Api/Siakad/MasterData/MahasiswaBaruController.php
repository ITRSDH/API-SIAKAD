<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use App\Http\Controllers\Controller;
use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\Prodi;
use App\Models\User;
use App\Services\StudentAngkatanResolverService;
use App\Services\StudentNimGeneratorService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MahasiswaBaruController extends Controller
{
    public function __construct(
        private readonly StudentAngkatanResolverService $studentAngkatanResolverService,
        private readonly StudentNimGeneratorService $studentNimGeneratorService
    ) {}

    public function index(): JsonResponse
    {
        try {
            // Memuat relasi yang relevan
            $mahasiswas = Mahasiswa::with(['prodi'])->where('status', '=', 'PMB')->get();
            $dataprodi = Prodi::all();

            return response()->json([
                'success' => true,
                'message' => 'Daftar Mahasiswa',
                'data' => [
                    'mahasiswa' => $mahasiswas,
                    'prodi' => $dataprodi,
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data mahasiswa.',
                'error' => $e->getMessage(), // Hanya tampilkan pesan error jika debug=true
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $mahasiswa = Mahasiswa::with(['prodi'])->find($id);

            if (! $mahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa tidak ditemukan.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail Mahasiswa',
                'data' => $mahasiswa,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data mahasiswa.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync data from API
     */
    public function sync(Request $request): JsonResponse
    {
        try {
            // Validasi request
            $request->validate([
                'id_periode_pendaftaran' => 'required',
            ]);

            $idPeriode = $request->id_periode_pendaftaran;

            // Get data from API dengan parameter periode
            $apiUrl = config('api.pmb_base_url').'mahasiswa?id_periode_pendaftaran='.$idPeriode;
            Log::info('Attempting to sync from: '.$apiUrl);

            $response = Http::get($apiUrl);

            Log::info('API Response Status: '.$response->status());

            if (! $response->successful()) {
                Log::error('API Sync Failed: '.$response->body());

                return response()->json(['success' => false, 'message' => 'Gagal menghubungi API server!'], 500);
            }

            $data = $response->json();

            if (! $data['success'] || ! isset($data['data'])) {
                return response()->json(['success' => false, 'message' => 'Format response API tidak valid!'], 400);
            }

            $apiMahasiswas = $data['data'];
            $syncCount = 0;
            $updateCount = 0;

            DB::beginTransaction();

            foreach ($apiMahasiswas as $apiMahasiswa) {
                // Check if mahasiswa exists by nomor_pendaftaran (using nim field as unique identifier)
                $existingMahasiswa = Mahasiswa::where('nim', $apiMahasiswa['nomor_pendaftaran'])->first();

                // Skip if mahasiswa already exists to prevent overwriting existing data
                if ($existingMahasiswa) {
                    Log::info('Skipping existing mahasiswa: '.$apiMahasiswa['nomor_pendaftaran']);

                    continue;
                }

                // Check if user already exists by email
                $existingUser = User::where('email', $apiMahasiswa['email'])->first();
                if ($existingUser) {
                    Log::info('Skipping existing user email: '.$apiMahasiswa['email']);

                    continue;
                }

                // Get prodi ID from prodi data
                $prodi = Prodi::where('kode_prodi', $apiMahasiswa['prodi']['kode_prodi'])->first();
                $idProdi = $prodi ? $prodi->id : null;

                $angkatan = $this->studentAngkatanResolverService->resolve(
                    null,
                    (string) ($apiMahasiswa['nomor_pendaftaran'] ?? '')
                );

                // Create User first
                $user = User::create([
                    'name' => $apiMahasiswa['nama_lengkap'],
                    'password' => bcrypt('12345678'), // Default password, should be changed later
                    'status' => 'aktif',
                ]);

                // Assign mahasiswa role
                $user->assignRole('mahasiswa');

                // Map API fields to Mahasiswa model fields
                $mahasiswaData = [
                    'user_id' => $user->id,
                    'nim' => $apiMahasiswa['nomor_pendaftaran'],
                    'nama_mahasiswa' => $apiMahasiswa['nama_lengkap'],
                    'jenis_kelamin' => $apiMahasiswa['jenis_kelamin'],
                    'tanggal_lahir' => $apiMahasiswa['tanggal_lahir'],
                    'alamat' => $apiMahasiswa['alamat'],
                    'no_hp' => $apiMahasiswa['no_hp'],
                    'asal_sekolah' => $apiMahasiswa['asal_sekolah'],
                    'id_prodi' => $idProdi,
                    'status' => 'PMB', // Status for pendaftar baru
                    'angkatan' => $apiMahasiswa['tahun_angkatan'] ?? null,
                    'nik' => $apiMahasiswa['nik'] ?? null,
                    'tempat_lahir' => $apiMahasiswa['tempat_lahir'] ?? null,
                    'tanggal_masuk' => $apiMahasiswa['tanggal_masuk'] ?? null,
                    // Fields that are not available from API but can be null
                    'id_dosen' => null,
                    'nama_orang_tua' => null,
                    'no_hp_orang_tua' => null,
                ];

                // Create mahasiswa record
                Mahasiswa::create($mahasiswaData);
                $syncCount++;
            }

            DB::commit();

            // Store last sync time
            session(['last_mahasiswa_sync' => now()->format('d M Y H:i:s')]);

            $message = "Sync berhasil! {$syncCount} data mahasiswa baru ditambahkan.";

            return response()->json(['success' => true, 'message' => $message, 'sync_count' => $syncCount]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: '.$e->getMessage()], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $mahasiswa = Mahasiswa::find($id);

            if (! $mahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa tidak ditemukan.',
                ], 404);
            }

            $data = $request->validate([
                'id_prodi' => 'sometimes|exists:prodi,id',
                'nim' => 'sometimes|string|max:55|unique:mahasiswa,nim,'.$id,
                'nama_mahasiswa' => 'sometimes|string|max:255',
                'jenis_kelamin' => 'sometimes|in:L,P',
                'tanggal_lahir' => 'sometimes|date',
                'alamat' => 'nullable|string',
                'no_hp' => 'nullable|string|max:15',
                'asal_sekolah' => 'nullable|string|max:255',
                'nama_orang_tua' => 'nullable|string|max:255',
                'no_hp_orang_tua' => 'nullable|string|max:15',
                'status' => 'sometimes|in:Aktif,Cuti,DO,Lulus,PMB',
                'angkatan' => 'sometimes|integer|min:1900|max:'.(date('Y') + 10),
                'jalur_masuk' => 'sometimes|nullable|string|max:50',
                'jenis_pendaftaran' => 'sometimes|nullable|string|max:50',
            ]);

            $idProdi = $data['id_prodi'] ?? $mahasiswa->id_prodi;
            $data['angkatan'] = $this->studentAngkatanResolverService->resolve(
                isset($data['angkatan']) ? (string) $data['angkatan'] : null,
                isset($data['nim']) ? (string) $data['nim'] : (string) $mahasiswa->nim
            );

            // Jika status diubah menjadi 'Aktif'
            if (isset($data['status']) && $data['status'] === 'Aktif') {
                $jalur = $data['jalur_masuk'] ?? $mahasiswa->jalur_masuk ?? $data['jenis_pendaftaran'] ?? $mahasiswa->jenis_pendaftaran ?? 'Reguler';
                $data['jalur_masuk'] = $jalur;
                $data['jenis_pendaftaran'] = $this->studentNimGeneratorService->isRplJalur($jalur) ? 'RPL' : 'Reguler';

                // Jika NIM tidak diisi manual atau masih sama dengan NIM pendaftaran lama
                $shouldGenerateNim = empty($data['nim']) || ($data['nim'] === $mahasiswa->nim && $mahasiswa->status === 'PMB');
                if ($shouldGenerateNim && $idProdi) {
                    $generatedNim = $this->studentNimGeneratorService->generate($idProdi, $data['angkatan'] ?? (int) date('Y'), $jalur);
                    $data['nim'] = $generatedNim;
                }

                if (empty($mahasiswa->tanggal_masuk)) {
                    $data['tanggal_masuk'] = now()->toDateString();
                }

                if ($mahasiswa->user_id) {
                    User::where('id', $mahasiswa->user_id)->update(['status' => 'aktif']);
                }
            }

            $mahasiswa->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Mahasiswa berhasil diperbarui.',
                'data' => $mahasiswa->fresh()->load('prodi'),
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui mahasiswa.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verifikasi calon mahasiswa PMB menjadi Mahasiswa Aktif sekali klik.
     */
    public function verify(Request $request, string $id): JsonResponse
    {
        try {
            $mahasiswa = Mahasiswa::find($id);

            if (! $mahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa tidak ditemukan.',
                ], 404);
            }

            $request->validate([
                'jalur_masuk' => 'nullable|string|in:Reguler,RPL,Pindahan,Alih Jenjang',
                'angkatan' => 'nullable|integer|min:1900|max:'.(date('Y') + 10),
            ]);

            $idProdi = $mahasiswa->id_prodi;
            if (! $idProdi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa belum memiliki Program Studi yang valid untuk generate NIM.',
                ], 422);
            }

            $angkatan = $request->input('angkatan')
                ?? $mahasiswa->angkatan
                ?? $this->studentAngkatanResolverService->extractFromNim($mahasiswa->nim)
                ?? (int) date('Y');

            $jalur = $request->input('jalur_masuk')
                ?? $mahasiswa->jalur_masuk
                ?? $mahasiswa->jenis_pendaftaran
                ?? 'Reguler';

            $newNim = $this->studentNimGeneratorService->generate($idProdi, $angkatan, $jalur);

            $mahasiswa->update([
                'nim' => $newNim,
                'status' => 'Aktif',
                'angkatan' => $angkatan,
                'jalur_masuk' => $jalur,
                'jenis_pendaftaran' => $this->studentNimGeneratorService->isRplJalur($jalur) ? 'RPL' : 'Reguler',
                'tanggal_masuk' => $mahasiswa->tanggal_masuk ?? now()->toDateString(),
            ]);

            if ($mahasiswa->user_id) {
                User::where('id', $mahasiswa->user_id)->update(['status' => 'aktif']);
            }

            return response()->json([
                'success' => true,
                'message' => "Mahasiswa berhasil diverifikasi menjadi Aktif dengan NIM: {$newNim}.",
                'data' => $mahasiswa->fresh()->load('prodi'),
                'nim' => $newNim,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat verifikasi mahasiswa: '.$e->getMessage(),
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $mahasiswa = Mahasiswa::find($id);

            if (! $mahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa tidak ditemukan.',
                ], 404);
            }

            // Simpan user_id untuk dihapus nanti
            $userId = $mahasiswa->user_id;

            // Hapus mahasiswa terlebih dahulu
            $mahasiswa->delete();

            // Hapus user terkait jika ada
            if ($userId) {
                User::find($userId)?->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Mahasiswa dan User terkait berhasil dihapus.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus mahasiswa.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
