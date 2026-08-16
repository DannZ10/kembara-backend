<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Requests\Booking\UpdateBookingStatusRequest;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(
        protected BookingService $bookingService
    ) {}

    public function store(StoreBookingRequest $request): JsonResponse
    {
        try {
            $booking = $this->bookingService->createBooking($request->user(), $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Booking berhasil dibuat',
                'data' => $booking,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function myBookings(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('limit', 10);
        $bookings = $this->bookingService->getUserBookings($request->user(), $request->all(), $perPage);

        return response()->json([
            'success' => true,
            'data' => $bookings->items(),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $booking = $this->bookingService->getUserBookingById($request->user(), $id);

        return response()->json([
            'success' => true,
            'data' => $booking,
        ]);
    }

    public function indexAdmin(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('limit', 10);
        $bookings = $this->bookingService->getAllBookingsAdmin($request->all(), $perPage);

        return response()->json([
            'success' => true,
            'data' => $bookings->items(),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
        ]);
    }

    public function updateStatus(UpdateBookingStatusRequest $request, string $id): JsonResponse
    {
        $booking = Booking::with('items')->findOrFail($id);
        $updated = $this->bookingService->updateBookingStatus($booking, $request->validated()['status']);

        return response()->json([
            'success' => true,
            'message' => 'Status booking berhasil diperbarui',
            'data' => $updated,
        ]);
    }

    public function verifyIdentity(Request $request, string $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);
        $isVerified = $request->boolean('verified', true);
        $verified = $this->bookingService->verifyIdentity($booking, $isVerified);

        return response()->json([
            'success' => true,
            'message' => $isVerified
                ? 'Verifikasi jaminan identitas berhasil'
                : 'Verifikasi jaminan identitas dibatalkan',
            'data' => $verified,
        ]);
    }
}
