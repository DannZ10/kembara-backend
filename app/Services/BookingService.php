<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Gear;
use App\Models\GearVariant;
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

            $subtotal = 0;
            $totalWeightKg = 0;
            $itemsToCreate = [];

            foreach ($data['items'] as $itemData) {
                $qty = $itemData['quantity'];

                // Pessimistic locking to prevent overbooking
                /** @var Gear $gear */
                $gear = Gear::where('id', $itemData['gear_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $gear->is_available) {
                    throw new \Exception("Gear '{$gear->name}' sedang tidak tersedia.");
                }

                // Stock is authoritative on the chosen variant when one is picked,
                // otherwise on the gear itself.
                $variantId = $itemData['gear_variant_id'] ?? null;
                $variantLabel = null;

                if ($variantId) {
                    /** @var GearVariant $variant */
                    $variant = GearVariant::where('id', $variantId)
                        ->where('gear_id', $gear->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $variant) {
                        throw new \Exception("Varian yang dipilih untuk gear '{$gear->name}' tidak ditemukan.");
                    }
                    if ($variant->stock < $qty) {
                        throw new \Exception("Stok varian '{$variant->label}' pada '{$gear->name}' tidak mencukupi ({$qty} unit). Sisa: {$variant->stock}");
                    }

                    $variant->decrement('stock', $qty);
                    $variantLabel = $variant->label;
                } else {
                    if ($gear->stock_available < $qty) {
                        throw new \Exception("Stok gear '{$gear->name}' tidak mencukupi untuk jumlah yang diminta ({$qty} unit). Sisa stok: {$gear->stock_available}");
                    }
                    $gear->decrement('stock_available', $qty);
                }

                $lineTotal = $gear->price_per_day * $qty * $durationDays;
                $subtotal += $lineTotal;
                $totalWeightKg += (float) $gear->weight_kg * $qty;

                $itemsToCreate[] = [
                    'gear_id' => $gear->id,
                    'gear_variant_id' => $variantId,
                    'variant_label' => $variantLabel,
                    'quantity' => $qty,
                    'price_per_day' => $gear->price_per_day,
                    'line_total' => $lineTotal,
                ];
            }

            // Distance is derived server-side from the pasted Google Maps link
            // (never trusted from the client). Weight drives the fee alongside it.
            $mapsUrl = $deliveryType === 'delivery' ? ($data['delivery_maps_url'] ?? null) : null;
            $distanceKm = $this->deliveryFeeService->resolveDistanceKm($mapsUrl);

            if ($deliveryType === 'delivery' && $distanceKm === null) {
                throw new \Exception('Tidak dapat membaca koordinat dari link Google Maps. Pastikan link menyertakan titik lokasi (share dari pin/tempat di Google Maps).');
            }

            $deliveryFee = $this->deliveryFeeService->calculateFee($deliveryType, $distanceKm, $totalWeightKg);
            $totalPrice = $subtotal + $deliveryFee;
            $bookingCode = 'KMB-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));

            /** @var Booking $booking */
            $booking = Booking::create([
                'user_id' => $user->id,
                'booking_code' => $bookingCode,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'duration_days' => $durationDays,
                'delivery_type' => $deliveryType,
                'delivery_address' => $data['delivery_address'] ?? null,
                'delivery_maps_url' => $mapsUrl,
                'delivery_distance_km' => $distanceKm,
                'delivery_fee' => $deliveryFee,
                'subtotal' => $subtotal,
                'total_price' => $totalPrice,
                'status' => BookingStatus::PENDING,
                'identity_verified' => false,
                'identity_type_1' => $data['identity_type_1'] ?? null,
                'identity_type_2' => $data['identity_type_2'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($itemsToCreate as $item) {
                $booking->items()->create($item);
            }

            ActivityLog::record($booking->id, 'booking.created', 'Booking dibuat oleh penyewa.');

            return $booking->load(['items.gear', 'user']);
        });

        // Stock deducted + revenue/low-stock aggregates changed.
        CacheHelper::flush(CacheHelper::CATALOG, CacheHelper::REPORTS);

        return $booking;
    }

    public function getUserBookings(User $user, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Booking::where('user_id', $user->id)
            ->with(['items.gear', 'payment', 'activities.actor:id,name']);

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
            ->with(['items.gear', 'payment', 'activities.actor:id,name'])
            ->firstOrFail();
    }

    public function getAllBookingsAdmin(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Booking::with(['user', 'items.gear', 'payment', 'activities.actor:id,name']);

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
                // Restore stock to wherever it was taken from (variant or gear).
                foreach ($booking->items as $item) {
                    if ($item->gear_variant_id && $item->variant) {
                        $item->variant->increment('stock', $item->quantity);
                    } else {
                        $item->gear->increment('stock_available', $item->quantity);
                    }
                }
            }

            $updates = ['status' => $targetStatus];

            // Stamp handover milestones the first time each is reached.
            if ($targetStatus === BookingStatus::ACTIVE && $booking->picked_up_at === null) {
                $updates['picked_up_at'] = now();
            }
            if ($targetStatus === BookingStatus::RETURNED && $booking->returned_at === null) {
                $updates['returned_at'] = now();
            }

            $booking->update($updates);

            $oldValue = $oldStatus instanceof BookingStatus ? $oldStatus->value : $oldStatus;
            ActivityLog::record($booking->id, 'status.changed', "Status booking: {$oldValue} → {$targetStatus->value}.");

            return $booking->fresh(['items.gear', 'user', 'payment']);
        });

        // Status transition may restore stock and shifts revenue/status aggregates.
        CacheHelper::flush(CacheHelper::CATALOG, CacheHelper::REPORTS);

        return $updated;
    }

    public function verifyIdentity(Booking $booking, bool $verified = true): Booking
    {
        $booking->update(['identity_verified' => $verified]);

        ActivityLog::record(
            $booking->id,
            'identity.verified',
            $verified ? 'Jaminan identitas diverifikasi admin.' : 'Verifikasi jaminan identitas dibatalkan.'
        );

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
