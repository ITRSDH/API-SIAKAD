<?php

namespace App\Http\Controllers\Api\Website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Website\StoreFaqRequest;
use App\Http\Requests\Website\UpdateFaqRequest;

use App\Models\Website\Faq;

class FaqController extends Controller
{
    public function index(Request $request)
    {
        try {
            $faqs = Faq::select('id', 'pertanyaan', 'jawaban', 'created_at')->orderBy('created_at', 'desc')->get();
            return response()->json([
                'success' => true,
                'message' => 'Daftar FAQ',
                'data' => $faqs
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data FAQ',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(StoreFaqRequest $request)
    {
        try {
            $faq = Faq::create($request->validated());
            return response()->json([
                'success' => true,
                'message' => 'FAQ berhasil ditambahkan',
                'data' => $faq
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan FAQ',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $faq = Faq::findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Detail FAQ',
                'data' => $faq
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'FAQ tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(UpdateFaqRequest $request, $id)
    {
        try {
            $faq = Faq::findOrFail($id);
            $faq->update($request->validated());
            return response()->json([
                'success' => true,
                'message' => 'FAQ berhasil diperbarui',
                'data' => $faq
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui FAQ',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $faq = Faq::findOrFail($id);
            $faq->delete();
            return response()->json([
                'success' => true,
                'message' => 'FAQ berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus FAQ',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
