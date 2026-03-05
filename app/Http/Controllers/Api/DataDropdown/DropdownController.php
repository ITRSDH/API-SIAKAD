<?php

namespace App\Http\Controllers\Api\DataDropdown;

use Illuminate\Http\Request;
use App\Services\DropdownService;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use InvalidArgumentException;
use Exception;

class DropdownController extends Controller
{
    public function index(Request $request, DropdownService $dropdownService): JsonResponse
    {
        try {

            // ✅ Validasi parameter wajib
            $request->validate([
                'type' => 'required|string'
            ]);

            $data = $dropdownService->get($request->type);

            return response()->json([
                'success' => true,
                'message' => 'Data dropdown berhasil diambil',
                'data' => $data
            ], 200);
        } catch (InvalidArgumentException $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data dropdown.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
