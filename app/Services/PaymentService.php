<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Midtrans\Config;
use Midtrans\Snap;

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
     * "offline mode" for local dev and the test suite.
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

        // With real credentials the SHA512 signature is mandatory: a missing
        // signature must be rejected, otherwise anyone could POST this webhook
        // and mark a booking as paid.
        if ($this->hasCredentials()) {
            if (! $signatureKey) {
                throw new \Exception('Missing Midtrans notification signature');
            }

            $expectedSignature = hash('sha512', $orderId.$statusCode.$grossAmount.Config::$serverKey);
            if (! hash_equals($expectedSignature, $signatureKey)) {
                throw new \Exception('Invalid Midtrans notification signature');
            }
        }

        /** @var Payment $payment */
        $payment = Payment::where('external_id', $orderId)->firstOrFail();
        $booking = $payment->booking;

        return DB::transaction(function () use ($payment, $booking, $transactionStatus, $payload) {
            $paymentMethod = $payload['payment_type'] ?? null;
            $payment->update(['method' => $paymentMethod]);

            if (in_array($transactionStatus, ['capture', 'settlement'], true)) {
                $payment->update([
                    'status' => PaymentStatus::PAID,
                    'paid_at' => now(),
                ]);
                $this->bookingService->updateBookingStatus($booking, BookingStatus::CONFIRMED);
            } elseif (in_array($transactionStatus, ['expire', 'cancel', 'deny'], true)) {
                $payment->update(['status' => PaymentStatus::EXPIRED]);
                $this->bookingService->updateBookingStatus($booking, BookingStatus::CANCELLED);
            }

            return $payment->fresh(['booking']);
        });
    }
}
