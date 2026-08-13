<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function createPayment(Request $request, string $bookingId): JsonResponse
    {
        $booking = Booking::where('id', $bookingId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        try {
            $payment = $this->paymentService->createSnapPayment($booking);

            return response()->json([
                'success' => true,
                'message' => 'Snap payment URL berhasil dibuat',
                'data' => [
                    'payment_url' => $payment->payment_url,
                    'external_id' => $payment->external_id,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'expired_at' => $payment->expired_at,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function handleWebhook(Request $request): JsonResponse
    {
        try {
            $payment = $this->paymentService->handleNotification($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Webhook notification processed successfully',
                'data' => $payment,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function getPaymentStatus(Request $request, string $bookingId): JsonResponse
    {
        $booking = Booking::where('id', $bookingId)
            ->where('user_id', $request->user()->id)
            ->with('payment')
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $booking->payment,
        ]);
    }
}
