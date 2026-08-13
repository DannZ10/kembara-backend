<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gear\StoreGearRequest;
use App\Http\Requests\Gear\UpdateGearRequest;
use App\Models\Gear;
use App\Services\GearService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GearController extends Controller
{
    public function __construct(
        protected GearService $gearService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('limit', 10);
        $gears = $this->gearService->getPaginatedGears($request->all(), $perPage);

        return response()->json([
            'success' => true,
            'data' => $gears->items(),
            'meta' => [
                'current_page' => $gears->currentPage(),
                'last_page' => $gears->lastPage(),
                'per_page' => $gears->perPage(),
                'total' => $gears->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $gear = $this->gearService->getGearById($id);

        return response()->json([
            'success' => true,
            'data' => $gear,
        ]);
    }

    public function showBySlug(string $slug): JsonResponse
    {
        $gear = $this->gearService->getGearBySlug($slug);

        return response()->json([
            'success' => true,
            'data' => $gear,
        ]);
    }

    public function store(StoreGearRequest $request): JsonResponse
    {
        $gear = $this->gearService->createGear($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Gear berhasil ditambahkan',
            'data' => $gear->load('category'),
        ], 201);
    }

    public function update(UpdateGearRequest $request, int $id): JsonResponse
    {
        $gear = Gear::findOrFail($id);
        $updated = $this->gearService->updateGear($gear, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Gear berhasil diperbarui',
            'data' => $updated,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $gear = Gear::findOrFail($id);
        $deactivated = $this->gearService->deactivateGear($gear);

        return response()->json([
            'success' => true,
            'message' => 'Gear berhasil dinonaktifkan',
            'data' => $deactivated,
        ]);
    }
}
