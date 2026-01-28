<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\MasterData\Mahasiswa;
use App\Models\MasterData\Prodi;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MahasiswaBaruController extends Controller
{
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
                    'mahasiswa'     => $mahasiswas,
                    'prodi'         => $dataprodi,
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
            $mahasiswa = Mahasiswa::with(['prodi'])->find($id);

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

     /**
     * Sync data from API
     */
    public function sync(Request $request): JsonResponse
    {
        try {
            // Validasi request
            $request->validate([
                'id_periode_pendaftaran' => 'required'
            ]);

            $idPeriode = $request->id_periode_pendaftaran;
            
            // Get data from API dengan parameter periode
            $apiUrl = config('api.pmb_base_url') . 'mahasiswa?id_periode_pendaftaran=' . $idPeriode;
            Log::info('Attempting to sync from: ' . $apiUrl);
            
            $response = Http::get($apiUrl);
            
            Log::info('API Response Status: ' . $response->status());
            
            if (!$response->successful()) {
                Log::error('API Sync Failed: ' . $response->body());
                return response()->json(['success' => false, 'message' => 'Gagal menghubungi API server!'], 500);
            }

            $data = $response->json();
            
            if (!$data['success'] || !isset($data['data'])) {
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
                    Log::info('Skipping existing mahasiswa: ' . $apiMahasiswa['nomor_pendaftaran']);
                    continue;
                }
                
                // Check if user already exists by email
                $existingUser = User::where('email', $apiMahasiswa['email'])->first();
                if ($existingUser) {
                    Log::info('Skipping existing user email: ' . $apiMahasiswa['email']);
                    continue;
                }
                
                // Get prodi ID from prodi data
                $prodi = Prodi::where('kode_prodi', $apiMahasiswa['prodi']['kode_prodi'])->first();
                $idProdi = $prodi ? $prodi->id : null;
                
                // Extract angkatan from tanggal_daftar (year)
                $angkatan = date('Y', strtotime($apiMahasiswa['tanggal_daftar']));
                
                // Create User first
                $user = User::create([
                    'name' => $apiMahasiswa['nama_lengkap'],
                    'email' => $apiMahasiswa['email'],
                    'password' => bcrypt('password123'), // Default password, should be changed later
                    'status' => 'aktif'
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
                    'angkatan' => $angkatan,
                    // Fields that are not available from API but can be null
                    'id_kelas_pararel' => null,
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
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $mahasiswa = Mahasiswa::find($id);

            if (!$mahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa tidak ditemukan.'
                ], 404);
            }

            $data = $request->validate([
                'id_prodi' => 'sometimes|exists:prodi,id',
                // 'id_kelas_pararel' => 'nullable|sometimes|exists:kelas_pararel,id',
                // 'id_dosen' => 'nullable|sometimes|exists:dosen,id',
                // 'user_id' => 'nullable|sometimes|exists:user,id',
                'nim' => 'sometimes|string|max:55|unique:mahasiswa,nim,' . $id,
                'nama_mahasiswa' => 'sometimes|string|max:255',
                'jenis_kelamin' => 'sometimes|in:L,P',
                'tanggal_lahir' => 'sometimes|date',
                'alamat' => 'nullable|string',
                'no_hp' => 'nullable|string|max:15',
                // 'email' => 'sometimes|email|unique:mahasiswa,email,' . $id,
                'asal_sekolah' => 'nullable|string|max:255',
                'nama_orang_tua' => 'nullable|string|max:255',
                'no_hp_orang_tua' => 'nullable|string|max:15',
                'status' => 'sometimes|in:Aktif,Cuti,DO,Lulus,PMB',
                'angkatan' => 'sometimes|integer|min:1900|max:' . (date('Y') + 10)
                // Tambahkan validasi untuk field lain jika ada
            ]);

            $mahasiswa->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Mahasiswa berhasil diperbarui.',
                'data' => $mahasiswa
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
            $mahasiswa = Mahasiswa::find($id);
            
            if (!$mahasiswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mahasiswa tidak ditemukan.'
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
                'message' => 'Mahasiswa dan User terkait berhasil dihapus.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus mahasiswa.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
