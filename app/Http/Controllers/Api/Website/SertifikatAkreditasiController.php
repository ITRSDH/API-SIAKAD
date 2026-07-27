<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use App\Models\Website\SertifikatAkreditasi;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class SertifikatAkreditasiController extends Controller
{
    public function index(Request $request)
    {
        try {
           $sertifikat = SertifikatAkreditasi::with([
                'fotos' => function ($q) {
                    $q->orderBy('created_at');
                }
            ])->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar sertifikat akreditasi',
                'data' => $sertifikat
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data sertifikat akreditasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request, ImageService $imageService)
    {
        try {
    
            Log::info('=== STORE SERTIFIKAT AKREDITASI START ===');
    
            Log::info('Request Data', [
                'nama' => $request->nama,
                'deskripsi' => $request->deskripsi,
                'hasFile' => $request->hasFile('fotos'),
                'jumlah_file' => $request->hasFile('fotos') ? count($request->file('fotos')) : 0,
            ]);
    
            $data = $request->validate([
                'nama' => 'required|string|max:255',
                'deskripsi' => 'required|string',
                'fotos' => 'required|array|min:1',
                'fotos.*' => 'required|image|mimes:jpeg,png,jpg,jpeg,webp|max:2048',
            ]);
    
            Log::info('Validasi berhasil');
    
            $sertifikat = SertifikatAkreditasi::create([
                'nama' => $data['nama'],
                'deskripsi' => $data['deskripsi'],
            ]);
    
            Log::info('Sertifikat berhasil dibuat', [
                'id' => $sertifikat->id
            ]);
    
            if ($request->hasFile('fotos')) {
    
                foreach ($request->file('fotos') as $index => $foto) {
    
                    Log::info('Memproses gambar', [
                        'index' => $index,
                        'original_name' => $foto->getClientOriginalName(),
                        'mime' => $foto->getMimeType(),
                        'size' => $foto->getSize(),
                    ]);
    
                    $path = $imageService->convertToWebpAndReplace(
                        $foto,
                        75,
                        'sertifikat_akreditasi'
                    );
    
                    Log::info('Gambar berhasil dikonversi', [
                        'path' => $path
                    ]);
    
                    $sertifikat->fotos()->create([
                        'foto' => $path,
                        'urutan' => $index + 1,
                    ]);
    
                    Log::info('Foto berhasil disimpan ke database', [
                        'urutan' => $index + 1
                    ]);
                }
    
            } else {
    
                Log::warning('Request tidak memiliki file fotos');
    
            }
    
            Log::info('=== STORE SERTIFIKAT AKREDITASI SUCCESS ===');
    
            return response()->json([
                'success' => true,
                'message' => 'Sertifikat akreditasi berhasil ditambahkan',
                'data' => $sertifikat->load('fotos')
            ], 201);
    
        } catch (ValidationException $e) {
    
            Log::error('Validation Error', [
                'errors' => $e->errors()
            ]);
    
            throw $e;
    
        } catch (\Exception $e) {
    
            Log::error('STORE SERTIFIKAT ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
    
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan sertifikat akreditasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $sertifikat = SertifikatAkreditasi::with('fotos')->findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Detail sertifikat akreditasi',
                'data' => $sertifikat
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sertifikat akreditasi tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

   public function update(Request $request, $id, ImageService $imageService)
    {
        try {
    
            $sertifikat = SertifikatAkreditasi::with('fotos')->findOrFail($id);
    
            $data = $request->validate([
                'nama' => 'sometimes|required|string|max:255',
                'deskripsi' => 'sometimes|required|string',
                'fotos' => 'sometimes|array|min:1',
                'fotos.*' => 'required|image|mimes:jpeg,png,jpg,jpeg,webp|max:2048',
            ]);
    
            $sertifikat->update([
                'nama' => $data['nama'] ?? $sertifikat->nama,
                'deskripsi' => $data['deskripsi'] ?? $sertifikat->deskripsi,
            ]);
    
            if ($request->hasFile('fotos')) {
    
                // Hapus seluruh file lama
                foreach ($sertifikat->fotos as $foto) {
                    $imageService->deletePublicFileIfExists($foto->foto);
                }
    
                // Hapus data foto lama
                $sertifikat->fotos()->delete();
    
                // Upload foto baru
                foreach ($request->file('fotos') as $index => $foto) {
    
                    $path = $imageService->convertToWebpAndReplace(
                        $foto,
                        75,
                        'sertifikat_akreditasi'
                    );
    
                    $sertifikat->fotos()->create([
                        'foto' => $path,
                        'urutan' => $index + 1,
                    ]);
                }
            }
    
            return response()->json([
                'success' => true,
                'message' => 'Sertifikat akreditasi berhasil diperbarui',
                'data' => $sertifikat->fresh()->load('fotos')
            ], 200);
    
        } catch (ValidationException $e) {
    
            throw $e;
    
        } catch (\Exception $e) {
    
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui sertifikat akreditasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

   public function destroy($id, ImageService $imageService)
    {
        try {
    
            $sertifikat = SertifikatAkreditasi::with('fotos')->findOrFail($id);
    
            // Hapus semua file foto
            foreach ($sertifikat->fotos as $foto) {
                $imageService->deletePublicFileIfExists($foto->foto);
            }
    
            // Hapus data sertifikat
            // Data pada tabel sertifikat_akreditasi_foto akan ikut terhapus
            // karena menggunakan cascadeOnDelete()
            $sertifikat->delete();
    
            return response()->json([
                'success' => true,
                'message' => 'Sertifikat akreditasi berhasil dihapus'
            ], 200);
    
        } catch (\Exception $e) {
    
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus sertifikat akreditasi',
                'error' => $e->getMessage()
            ], 500);
    
        }
    }
}
