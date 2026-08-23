<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private const DELIVERY_KEYS = [
        'delivery_base_fee',
        'delivery_free_radius_km',
        'delivery_free_weight_kg',
        'delivery_per_km_fee',
        'delivery_per_kg_fee',
        'basecamp_lat',
        'basecamp_lng',
    ];

    public function delivery(): JsonResponse
    {
        $data = [];
        foreach (self::DELIVERY_KEYS as $key) {
            $data[$key] = (float) Setting::get($key, 0);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function updateDelivery(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'delivery_base_fee' => 'required|numeric|min:0',
            'delivery_free_radius_km' => 'required|numeric|min:0',
            'delivery_free_weight_kg' => 'required|numeric|min:0',
            'delivery_per_km_fee' => 'required|numeric|min:0',
            'delivery_per_kg_fee' => 'required|numeric|min:0',
            'basecamp_lat' => 'required|numeric|between:-90,90',
            'basecamp_lng' => 'required|numeric|between:-180,180',
        ]);

        foreach ($validated as $key => $value) {
            Setting::put($key, $value);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan biaya antar berhasil diperbarui',
            'data' => $validated,
        ]);
    }
}
