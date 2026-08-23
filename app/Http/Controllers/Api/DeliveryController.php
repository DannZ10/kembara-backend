<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gear;
use App\Services\DeliveryFeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(
        protected DeliveryFeeService $deliveryFeeService
    ) {}

    /**
     * Live delivery-fee preview for the cart. Weight is recomputed server-side
     * from the gear list when items are supplied (authoritative), otherwise the
     * client-provided weight_kg is used.
     */
    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'delivery_type' => 'required|in:pickup,delivery',
            'delivery_maps_url' => 'required_if:delivery_type,delivery|nullable|string|max:1000',
            'items' => 'nullable|array',
            'items.*.gear_id' => 'required_with:items|integer|exists:gears,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'weight_kg' => 'nullable|numeric|min:0',
        ]);

        $weightKg = (float) ($validated['weight_kg'] ?? 0);

        if (! empty($validated['items'])) {
            $weights = Gear::whereIn('id', array_column($validated['items'], 'gear_id'))->pluck('weight_kg', 'id');
            $weightKg = 0;
            foreach ($validated['items'] as $item) {
                $weightKg += (float) ($weights[$item['gear_id']] ?? 0) * $item['quantity'];
            }
        }

        $result = $this->deliveryFeeService->quote(
            $validated['delivery_type'],
            $validated['delivery_maps_url'] ?? null,
            $weightKg
        );

        if ($validated['delivery_type'] === 'delivery' && $result['distance_km'] === null) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat membaca lokasi dari link Google Maps. Pastikan link berisi titik lokasi (share dari pin/tempat).',
            ], 422);
        }

        return response()->json(['success' => true, 'data' => $result]);
    }
}
