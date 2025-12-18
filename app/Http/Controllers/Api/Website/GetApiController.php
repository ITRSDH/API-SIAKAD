<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use App\Models\MasterData\Prodi;
use Illuminate\Http\Request;
use App\Models\Website\Prestasi;
use App\Models\Website\Pengumuman;
use App\Models\Website\LandingContent;
use App\Models\Website\Beasiswa;
use App\Models\Website\Berita;
use App\Models\Website\Galeri;
use App\Models\Website\Faq;
use App\Models\Website\Ormawa;
use App\Models\Website\ProfileKampus;

class GetApiController extends Controller
{
    public function prestasi()
    {
        try {
            $prestasi = Prestasi::paginate(10);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Data prestasi berhasil diambil',
                'data' => $prestasi
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data prestasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function prestasiDetail($id)
    {
        try {
            $prestasi = Prestasi::findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Detail prestasi',
                'data' => $prestasi
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Prestasi tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function pengumuman()
    {
        try {
            $pengumuman = Pengumuman::paginate(10);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Data pengumuman berhasil diambil',
                'data' => $pengumuman
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data pengumuman',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function pengumumanDetail($id)
    {
        try {
            $pengumuman = Pengumuman::findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Detail pengumuman',
                'data' => $pengumuman
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pengumuman tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function beasiswa()
    {
        try {
            $beasiswa = Beasiswa::paginate(10);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Data beasiswa berhasil diambil',
                'data' => $beasiswa
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data beasiswa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function beasiswaDetail($id)
    {
        try {
            $beasiswa = Beasiswa::findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Detail beasiswa',
                'data' => $beasiswa
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Beasiswa tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function berita()
    {
        try {
            $berita = Berita::paginate(10);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Data berita berhasil diambil',
                'data' => $berita
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data berita',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function beritaDetail($id)
    {
        try {
            $berita = Berita::findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Detail berita',
                'data' => $berita
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Berita tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function galeri()
    {
        try {
            $galeri = Galeri::paginate(10);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Data galeri berhasil diambil',
                'data' => $galeri
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data galeri',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function galeriDetail($id)
    {
        try {
            $galeri = Galeri::findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Detail galeri',
                'data' => $galeri
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Galeri tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function faq()
    {
        try {
            $faqs = Faq::all();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Data FAQ berhasil diambil',
                'data' => $faqs
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data FAQ',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function ormawa()
    {
        try {
            $ormawa = Ormawa::paginate(10);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Data ormawa berhasil diambil',
                'data' => $ormawa
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data ormawa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function ormawaDetail($id)
    {
        try {
            $ormawa = Ormawa::findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Detail ormawa',
                'data' => $ormawa
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ormawa tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function prodi()
    {
        try {
            $prodi = Prodi::all();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Data prodi berhasil diambil',
                'data' => $prodi
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data prodi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function prodiDetail($id)
    {
        try {
            $prodi = Prodi::with('prestasi')->findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Detail prodi',
                'data' => $prodi
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Prodi tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function prodiPrestasi($id)
    {
        try {
            $prodi = Prodi::findOrFail($id);
            $prestasi = $prodi->prestasi()->get();
            
            return response()->json([
                'success' => true,
                'message' => 'Data prestasi berdasarkan prodi',
                'data' => [
                    'prodi' => $prodi,
                    'prestasi' => $prestasi
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Prodi tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function landingContent()
    {
        try {
            $content = LandingContent::first();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Data landing content berhasil diambil',
                'data' => $content
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data landing content',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function profileKampus()
    {
        try {
            $profile = ProfileKampus::first();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Data profile kampus berhasil diambil',
                'data' => $profile
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data profile kampus',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
