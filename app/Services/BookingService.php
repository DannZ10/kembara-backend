<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Gear;
use App\Models\User;
use App\Support\Cache\CacheHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        protected DeliveryFeeService $deliveryFeeService
    ) {}

    public function createBooking(User $user, array $data): Booking
    {
        $booking = DB::transaction(function () use ($user, $data) {
            $startDate = new \DateTime($data['start_date']);
            $endDate = new \DateTime($data['end_date']);
            $diffDays = $startDate->diff($endDate)->days;
            $durationDays = max(1, $diffDays);

            $deliveryType = $data['delivery_type'];
            $distanceKm = $data['delivery_distance_km'] ?? null;
            $deliveryFee = $this->deliveryFeeService->calculateFee($deliveryType, $distanceKm);

            $subtotal = 0;
            $itemsToCreate = [];

            foreach ($data['items'] as $itemData) {
                // Pessimistic locking to prevent overbooking
                /** @var Gear $gear */
                $gear = Gear::where('id', $itemData['gear_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $gear->is_available || $gear->stock_available < $itemData['quantity']) {
                    throw new \Exception("Stok gear '{$gear->name}' tidak mencukupi untuk jumlah yang diminta ({$itemData['quantity']} unit). Sisa stok: {$gear->stock_available}");
                }

                $lineTotal = $gear->price_per_day * $itemData['quantity'] * $durationDays;
                $subtotal += $lineTotal;

                $itemsToCreate[] = [
                    'gear_id' => $gear->id,
                    'quantity' => $itemData['quantity'],
                    'price_per_day' => $gear->price_per_day,
                    'line_total' => $lineTotal,
                ];

                // Deduct available stock
                $gear->decrement('stock_available', $itemData['quantity']);
            }

            $totalPrice = $subtotal + $deliveryFee;
            $bookingCode = 'GN-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));

            /** @var Booking $booking */
            $booking = Booking::create([
                'user_id' => $user->id,
                'booking_code' => $bookingCode,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'duration_days' => $durationDays,
                'delivery_type' => $deliveryType,
                'delivery_address' => $data['delivery_address'] ?? null,
                'delivery_distance_km' => $distanceKm,
                'delivery_fee' => $deliveryFee,
                'subtotal' => $subtotal,
                'total_price' => $totalPrice,
                'status' => BookingStatus::PENDING,
                'identity_verified' => false,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($itemsToCreate as $item) {
                $booking->items()->create($item);
            }

            return $booking->load(['items.gear', 'user']);
        });

        // Stock deducted + revenue/low-stock aggregates changed.
        CacheHelper::flush(CacheHelper::CATALOG, CacheHelper::REPORTS);

        return $booking;
    }

    public function getUserBookings(User $user, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Booking::where('user_id', $user->id)
            ->with(['items.gear', 'payment']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $this->applyDateRange($query, $filters);

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getUserBookingById(User $user, string $id): Booking
    {
        return Booking::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['items.gear', 'payment'])
            ->firstOrFail();
    }

    public function getAllBookingsAdmin(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Booking::with(['user', 'items.gear', 'payment']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('booking_code', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $this->applyDateRange($query, $filters);

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function updateBookingStatus(Booking $booking, BookingStatus|string $newStatus): Booking
    {
        $targetStatus = $newStatus instanceof BookingStatus ? $newStatus : BookingStatus::from($newStatus);

        $updated = DB::transaction(function () use ($booking, $targetStatus) {
            $oldStatus = $booking->status;

            if (($targetStatus === BookingStatus::CANCELLED || $targetStatus === BookingStatus::RETURNED)
                && ($oldStatus !== BookingStatus::CANCELLED && $oldStatus !== BookingStatus::RETURNED)) {
                // Restore gear stocks
                foreach ($booking->items as $item) {
                    $item->gear->increment('stock_available', $item->quantity);
                }
            }

            $booking->update(['status' => $targetStatus]);

            return $booking->fresh(['items.gear', 'user', 'payment']);
        });

        // Status transition may restore stock and shifts revenue/status aggregates.
        CacheHelper::flush(CacheHelper::CATALOG, CacheHelper::REPORTS);

        return $updated;
    }

    public function verifyIdentity(Booking $booking, bool $verified = true): Booking
    {
        $booking->update(['identity_verified' => $verified]);

        return $booking->fresh();
    }

    /**
     * Filter a booking query by created_at date range (date_from / date_to).
     */
    private function applyDateRange($query, array $filters): void
    {
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
    }
}
