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
        Config::$serverKey = config('services.midtrans.server_key', env('MIDTRANS_SERVER_KEY', 'SB-Mid-server-TESTKEY'));
        Config::$isProduction = config('services.midtrans.is_production', false);
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function createSnapPayment(Booking $booking): Payment
    {
        // Return existing pending payment if available
        if ($booking->payment && $booking->payment->status === PaymentStatus::PENDING) {
            return $booking->payment;
        }

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
        ];

        $snapUrl = 'https://app.sandbox.midtrans.com/snap/v2/vtweb/'.$orderId;

        // Try getting Snap URL from Midtrans SDK if keys are valid
        try {
            if (Config::$serverKey !== 'SB-Mid-server-TESTKEY') {
                $snapUrl = Snap::getRedirectUrl($params);
            }
        } catch (\Exception $e) {
            // Fallback for dev/testing environment
            $snapUrl = 'https://app.sandbox.midtrans.com/snap/v2/vtweb/'.$orderId;
        }

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

        // Verify SHA512 signature if server_key is set
        $serverKey = Config::$serverKey;
        if ($serverKey && $serverKey !== 'SB-Mid-server-TESTKEY' && $signatureKey) {
            $expectedSignature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);
            if ($signatureKey !== $expectedSignature) {
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
