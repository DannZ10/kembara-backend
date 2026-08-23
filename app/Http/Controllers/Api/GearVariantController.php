<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gear;
use App\Models\GearVariant;
use App\Support\Cache\CacheHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GearVariantController extends Controller
{
    public function store(Request $request, int $gearId): JsonResponse
    {
        $gear = Gear::findOrFail($gearId);
        $variant = $gear->variants()->create($this->validated($request));
        CacheHelper::flush(CacheHelper::CATALOG);

        return response()->json([
            'success' => true,
            'message' => 'Varian ditambahkan',
            'data' => $variant,
        ], 201);
    }

    public function update(Request $request, int $gearId, int $variantId): JsonResponse
    {
        $variant = GearVariant::where('gear_id', $gearId)->findOrFail($variantId);
        $variant->update($this->validated($request));
        CacheHelper::flush(CacheHelper::CATALOG);

        return response()->json([
            'success' => true,
            'message' => 'Varian diperbarui',
            'data' => $variant->fresh(),
        ]);
    }

    public function destroy(int $gearId, int $variantId): JsonResponse
    {
        $variant = GearVariant::where('gear_id', $gearId)->findOrFail($variantId);
        $variant->delete();
        CacheHelper::flush(CacheHelper::CATALOG);

        return response()->json([
            'success' => true,
            'message' => 'Varian dihapus',
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'size' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
            'stock' => 'required|integer|min:0',
        ]);

        if (blank($data['size'] ?? null) && blank($data['color'] ?? null)) {
            throw ValidationException::withMessages([
                'size' => 'Isi minimal salah satu: ukuran atau warna.',
            ]);
        }

        return $data;
    }
}
