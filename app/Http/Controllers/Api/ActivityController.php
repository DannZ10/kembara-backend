<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    /** Global activity feed for the admin dashboard (newest first). */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('limit', 30);

        $query = ActivityLog::with(['actor:id,name,email', 'booking:id,booking_code'])
            ->latest('created_at');

        if ($request->filled('booking_id')) {
            $query->where('booking_id', $request->input('booking_id'));
        }
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('booking', fn ($q) => $q->where('booking_code', 'like', "%{$search}%"));
        }

        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
