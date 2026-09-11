<?php

namespace App\Http\Controllers\Api\DataDropdown;

use App\Http\Controllers\Controller;
use App\Services\DropdownService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class DropdownController extends Controller
{
    public function index(Request $request, DropdownService $dropdownService): JsonResponse
    {
        try {

            // ✅ Validasi parameter wajib
            $request->validate([
                'type' => 'required|string',
            ]);

            $data = $dropdownService->get($request->type);

            return response()->json([
                'success' => true,
                'message' => 'Data dropdown berhasil diambil',
                'data' => $data,
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
