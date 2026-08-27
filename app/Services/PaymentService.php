<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction;

class PaymentService
{
    public function __construct(
        protected BookingService $bookingService
    ) {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = (bool) config('services.midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function createSnapPayment(Booking $booking): Payment
    {
        // Return existing pending payment if available
        if ($booking->payment && $booking->payment->status === PaymentStatus::PENDING) {
            return $booking->payment;
        }

        $booking->loadMissing(['items.gear', 'user']);

        $orderId = $booking->booking_code.'-'.time();
        $grossAmount = (int) round($booking->total_price);

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $booking->user->name,
                'email' => $booking->user->email,
                'phone' => $booking->user->phone ?? '08123456789',
            ],
            'expiry' => [
                'start_time' => date('Y-m-d H:i:s O'),
                'unit' => 'hour',
                'duration' => 24,
            ],
            'callbacks' => [
                'finish' => config('services.midtrans.finish_url'),
            ],
        ];

        // Show the rented gear on the Snap page. Midtrans recomputes
        // gross_amount from item_details, so only attach them when the sum
        // matches the booking total exactly.
        $items = $this->buildItemDetails($booking);
        if ($items !== null) {
            $params['item_details'] = $items;
        }

        // With real credentials, call Midtrans. Failures propagate to the
        // controller as a 422 instead of silently returning an unusable URL.
        $snapUrl = $this->hasCredentials()
            ? Snap::getSnapUrl($params)
            : 'https://app.sandbox.midtrans.com/snap/v2/vtweb/'.$orderId;

        return Payment::updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'gateway' => 'midtrans',
                'external_id' => $orderId,
                'payment_url' => $snapUrl,
                'amount' => $booking->total_price,
                'status' => PaymentStatus::PENDING,
                'expired_at' => now()->addHours(24),
            ]
        );
    }

    /**
     * Real Midtrans credentials configured? The shipped placeholder means
     * "offline mode" for local dev and the test suite. NOTE: this only gates
     * Snap URL creation — webhook signature verification never skips (fail closed).
     */
    private function hasCredentials(): bool
    {
        $key = (string) Config::$serverKey;

        return $key !== '' && ! str_contains($key, 'TESTKEY');
    }

    /**
     * Snap line items for the booking, or null when the computed sum does not
     * match the stored total (never let Midtrans charge a different amount).
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function buildItemDetails(Booking $booking): ?array
    {
        $items = [];

        foreach ($booking->items as $item) {
            $items[] = [
                'id' => (string) $item->gear_id,
                'name' => mb_substr($item->gear?->name ?? 'Gear', 0, 50),
                'price' => (int) round($item->price_per_day * $booking->duration_days),
                'quantity' => (int) $item->quantity,
            ];
        }

        $deliveryFee = (int) round($booking->delivery_fee);
        if ($deliveryFee > 0) {
            $items[] = [
                'id' => 'DELIVERY',
                'name' => 'Biaya Pengiriman',
                'price' => $deliveryFee,
                'quantity' => 1,
            ];
        }

        $sum = array_sum(array_map(fn ($i) => $i['price'] * $i['quantity'], $items));

        return $sum === (int) round($booking->total_price) ? $items : null;
    }

    public function handleNotification(array $payload): Payment
    {
        $orderId = $payload['order_id'] ?? null;
        $statusCode = $payload['status_code'] ?? null;
        $grossAmount = $payload['gross_amount'] ?? null;
        $signatureKey = $payload['signature_key'] ?? null;
        $transactionStatus = $payload['transaction_status'] ?? null;

        if (! $orderId) {
            throw new \Exception('Invalid notification payload: Missing order_id');
        }

        // FAIL CLOSED: the SHA512 signature is mandatory whenever any server key
        // is configured — including the local/test placeholder. An unsigned or
        // badly-signed webhook must never be able to flip a booking to PAID,
        // regardless of environment. Snap *creation* keeps its offline mode via
        // hasCredentials(), but *verification* never skips.
        if (! Config::$serverKey) {
            throw new \Exception('Midtrans server key is not configured');
        }

        if (! $signatureKey) {
            throw new \Exception('Missing Midtrans notification signature');
        }

        $expectedSignature = hash('sha512', $orderId.$statusCode.$grossAmount.Config::$serverKey);
        if (! hash_equals($expectedSignature, (string) $signatureKey)) {
            throw new \Exception('Invalid Midtrans notification signature');
        }

        /** @var Payment $payment */
        $payment = Payment::where('external_id', $orderId)->firstOrFail();
        $booking = $payment->booking;

        // Defense-in-depth: the signed gross_amount must match what we charged.
        // A mismatch means replayed/tampered payload even with a valid signature.
        if ($grossAmount !== null && abs((float) $grossAmount - (float) $payment->amount) >= 0.01) {
            throw new \Exception('Notification amount does not match the recorded payment');
        }

        return $this->applyTransition($payment, $booking, $transactionStatus, $payload['payment_type'] ?? null);
    }

    /**
     * Apply a Midtrans transaction_status to a payment + its booking. Shared by
     * the webhook and the active gateway sync. Idempotent: a paid payment never
     * downgrades, and duplicate paid events are no-ops.
     */
    private function applyTransition(Payment $payment, Booking $booking, ?string $transactionStatus, ?string $paymentType): Payment
    {
        $isPaidEvent = in_array($transactionStatus, ['capture', 'settlement'], true);
        $isFailEvent = in_array($transactionStatus, ['expire', 'cancel', 'deny'], true);

        // Unknown status (pending/refund/partial-*) or already paid — nothing to do.
        if ((! $isPaidEvent && ! $isFailEvent) || $payment->status === PaymentStatus::PAID) {
            return $payment->fresh(['booking']);
        }

        return DB::transaction(function () use ($payment, $booking, $transactionStatus, $isPaidEvent, $paymentType) {
            $payment->update(['method' => $paymentType]);

            if ($isPaidEvent) {
                $payment->update([
                    'status' => PaymentStatus::PAID,
                    'paid_at' => now(),
                ]);

                // Only PENDING bookings are confirmed here. If the booking was
                // already CANCELLED (stock restored), auto-confirming could
                // oversell — log it for manual reconciliation instead.
                if ($booking->status === BookingStatus::PENDING) {
                    $this->bookingService->updateBookingStatus($booking, BookingStatus::CONFIRMED);
                    ActivityLog::record($booking->id, 'payment.paid', 'Pembayaran diterima via Midtrans ('.($paymentType ?? 'online').').');
                } else {
                    ActivityLog::record($booking->id, 'payment.paid.late', 'Dana diterima untuk booking berstatus '.$booking->status->value.' — perlu rekonsiliasi manual.');
                }
            } else {
                $payment->update(['status' => PaymentStatus::EXPIRED]);
                $this->bookingService->updateBookingStatus($booking, BookingStatus::CANCELLED);
                ActivityLog::record($booking->id, 'payment.failed', 'Pembayaran gagal/kadaluarsa ('.$transactionStatus.').');
            }

            return $payment->fresh(['booking']);
        });
    }

    /**
     * Pull the real status from Midtrans for a still-pending online payment and
     * apply it. Makes booking status resilient to dropped/late webhooks (e.g.
     * Render cold starts) — the status syncs when the customer or admin views
     * their bookings, no manual confirmation needed.
     */
    public function syncFromGateway(Payment $payment): Payment
    {
        if ($payment->status !== PaymentStatus::PENDING
            || $payment->gateway !== 'midtrans'
            || ! $payment->external_id
            || ! $this->hasCredentials()) {
            return $payment;
        }

        try {
            $status = (array) Transaction::status($payment->external_id);
        } catch (\Throwable $e) {
            return $payment; // not found yet / transient — leave pending
        }

        $transactionStatus = $status['transaction_status'] ?? null;
        if (! $transactionStatus) {
            return $payment;
        }

        return $this->applyTransition(
            $payment,
            $payment->booking,
            $transactionStatus,
            $status['payment_type'] ?? null
        );
    }

    /**
     * Reconcile still-pending Midtrans payments (optionally scoped to one user)
     * against the gateway before listing bookings, so both admin and customer
     * riwayat reflect the real payment status without waiting for a webhook.
     * Bounded to recent attempts to keep the sweep cheap.
     */
    public function syncPendingBookings(?User $user = null): void
    {
        if (! $this->hasCredentials()) {
            return;
        }

        Payment::query()
            ->where('status', PaymentStatus::PENDING)
            ->where('gateway', 'midtrans')
            ->whereNotNull('external_id')
            ->where('created_at', '>=', now()->subDays(3))
            ->when($user, fn ($q) => $q->whereHas('booking', fn ($b) => $b->where('user_id', $user->id)))
            ->with('booking')
            ->get()
            ->each(fn (Payment $p) => $this->syncFromGateway($p));
    }
}
