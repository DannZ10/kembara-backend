<?php

namespace Tests\Feature\Api;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Gear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentAndReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Midtrans signature: SHA512(order_id + status_code + gross_amount + serverKey).
     * The test suite runs with the SB-Mid-server-TESTKEY placeholder, which the
     * webhook handler now verifies against (fail closed) just like a real key.
     */
    private function midtransSignature(string $orderId, string $statusCode, string $grossAmount): string
    {
        return hash('sha512', $orderId.$statusCode.$grossAmount.'SB-Mid-server-TESTKEY');
    }

    public function test_can_create_payment_snap_url_for_booking(): void
    {
        $customer = User::where('role', UserRole::CUSTOMER)->first();
        $gear = Gear::first();

        Sanctum::actingAs($customer);
        $bookingRes = $this->postJson('/api/bookings', [
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(3)->format('Y-m-d'),
            'delivery_type' => 'pickup',
            'identity_type_1' => 'KTP',
            'identity_type_2' => 'SIM',
            'identity_agreed' => true,
            'items' => [
                ['gear_id' => $gear->id, 'quantity' => 1],
            ],
        ]);

        $bookingId = $bookingRes->json('data.id');

        $payRes = $this->postJson("/api/bookings/{$bookingId}/payment");
        $payRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $this->assertNotEmpty($payRes->json('data.payment_url'));
        $this->assertNotEmpty($payRes->json('data.external_id'));
    }

    public function test_webhook_settlement_marks_payment_paid_and_booking_confirmed(): void
    {
        $customer = User::where('role', UserRole::CUSTOMER)->first();
        $gear = Gear::first();

        Sanctum::actingAs($customer);
        $bookingRes = $this->postJson('/api/bookings', [
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(2)->format('Y-m-d'),
            'delivery_type' => 'pickup',
            'identity_type_1' => 'KTP',
            'identity_type_2' => 'SIM',
            'identity_agreed' => true,
            'items' => [
                ['gear_id' => $gear->id, 'quantity' => 1],
            ],
        ]);

        $bookingId = $bookingRes->json('data.id');
        $payRes = $this->postJson("/api/bookings/{$bookingId}/payment");
        $orderId = $payRes->json('data.external_id');

        // Simulate Midtrans Webhook Callback
        $webhookRes = $this->postJson('/api/payments/webhook', [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => (string) round($gear->price_per_day),
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
            'signature_key' => $this->midtransSignature($orderId, '200', (string) round($gear->price_per_day)),
        ]);

        $webhookRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'status' => BookingStatus::CONFIRMED->value,
        ]);
    }

    public function test_webhook_expire_restores_gear_stock(): void
    {
        $customer = User::where('role', UserRole::CUSTOMER)->first();
        $gear = Gear::first();
        $initialStock = $gear->stock_available;

        Sanctum::actingAs($customer);
        $bookingRes = $this->postJson('/api/bookings', [
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(2)->format('Y-m-d'),
            'delivery_type' => 'pickup',
            'identity_type_1' => 'KTP',
            'identity_type_2' => 'SIM',
            'identity_agreed' => true,
            'items' => [
                ['gear_id' => $gear->id, 'quantity' => 2],
            ],
        ]);

        $bookingId = $bookingRes->json('data.id');
        $this->assertEquals($initialStock - 2, $gear->fresh()->stock_available);

        $payRes = $this->postJson("/api/bookings/{$bookingId}/payment");
        $orderId = $payRes->json('data.external_id');

        // Simulate Midtrans Webhook Expire
        $webhookRes = $this->postJson('/api/payments/webhook', [
            'order_id' => $orderId,
            'status_code' => '202',
            'gross_amount' => (string) round($gear->price_per_day * 2),
            'transaction_status' => 'expire',
            'signature_key' => $this->midtransSignature($orderId, '202', (string) round($gear->price_per_day * 2)),
        ]);

        $webhookRes->assertStatus(200)
            ->assertJsonPath('data.status', 'expired');

        // Stock restored!
        $this->assertEquals($initialStock, $gear->fresh()->stock_available);
    }

    public function test_webhook_without_signature_is_rejected(): void
    {
        $customer = User::where('role', UserRole::CUSTOMER)->first();
        $gear = Gear::first();

        Sanctum::actingAs($customer);
        $bookingRes = $this->postJson('/api/bookings', [
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(2)->format('Y-m-d'),
            'delivery_type' => 'pickup',
            'identity_type_1' => 'KTP',
            'identity_type_2' => 'SIM',
            'identity_agreed' => true,
            'items' => [
                ['gear_id' => $gear->id, 'quantity' => 1],
            ],
        ]);

        $payRes = $this->postJson("/api/bookings/{$bookingRes->json('data.id')}/payment");
        $orderId = $payRes->json('data.external_id');

        // Unsigned forgery attempt — must be refused even with the placeholder key.
        $webhookRes = $this->postJson('/api/payments/webhook', [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => (string) round($gear->price_per_day),
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
        ]);

        $webhookRes->assertStatus(400);
        $this->assertDatabaseHas('payments', ['external_id' => $orderId, 'status' => PaymentStatus::PENDING->value]);
    }

    public function test_webhook_with_tampered_amount_is_rejected(): void
    {
        $customer = User::where('role', UserRole::CUSTOMER)->first();
        $gear = Gear::first();

        Sanctum::actingAs($customer);
        $bookingRes = $this->postJson('/api/bookings', [
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(2)->format('Y-m-d'),
            'delivery_type' => 'pickup',
            'identity_type_1' => 'KTP',
            'identity_type_2' => 'SIM',
            'identity_agreed' => true,
            'items' => [
                ['gear_id' => $gear->id, 'quantity' => 1],
            ],
        ]);

        $payRes = $this->postJson("/api/bookings/{$bookingRes->json('data.id')}/payment");
        $orderId = $payRes->json('data.external_id');
        $realAmount = (string) round($gear->price_per_day);
        $tamperedAmount = '1';

        $webhookRes = $this->postJson('/api/payments/webhook', [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => $tamperedAmount,
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
            // Signature is valid for the TAMPERED amount, but the amount does
            // not match what was recorded — must still be rejected.
            'signature_key' => $this->midtransSignature($orderId, '200', $tamperedAmount),
        ]);

        $webhookRes->assertStatus(400);
        $this->assertDatabaseHas('payments', ['external_id' => $orderId, 'status' => PaymentStatus::PENDING->value]);
        $this->assertNotEquals($realAmount, $tamperedAmount);
    }

    public function test_webhook_duplicate_settlement_is_idempotent_and_never_downgrades(): void
    {
        $customer = User::where('role', UserRole::CUSTOMER)->first();
        $gear = Gear::first();

        Sanctum::actingAs($customer);
        $bookingRes = $this->postJson('/api/bookings', [
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(2)->format('Y-m-d'),
            'delivery_type' => 'pickup',
            'identity_type_1' => 'KTP',
            'identity_type_2' => 'SIM',
            'identity_agreed' => true,
            'items' => [
                ['gear_id' => $gear->id, 'quantity' => 1],
            ],
        ]);

        $bookingId = $bookingRes->json('data.id');
        $payRes = $this->postJson("/api/bookings/{$bookingId}/payment");
        $orderId = $payRes->json('data.external_id');
        $amount = (string) round($gear->price_per_day);

        $signed = fn (string $status) => [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => $amount,
            'transaction_status' => $status,
            'signature_key' => $this->midtransSignature($orderId, '200', $amount),
        ];

        $this->postJson('/api/payments/webhook', $signed('settlement'))->assertStatus(200);

        // Duplicate settlement: no error, no double side-effects.
        $this->postJson('/api/payments/webhook', $signed('settlement'))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'paid');

        // Late expire AFTER payment: must NOT downgrade booking or refund stock.
        $lateExpire = $signed('expire');
        $lateExpire['status_code'] = '202';
        $lateExpire['signature_key'] = $this->midtransSignature($orderId, '202', $amount);
        $this->postJson('/api/payments/webhook', $lateExpire)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('bookings', ['id' => $bookingId, 'status' => BookingStatus::CONFIRMED->value]);
    }

    public function test_admin_can_view_reports(): void
    {
        $admin = User::where('role', UserRole::ADMIN)->first();
        Sanctum::actingAs($admin);

        $dashboardRes = $this->getJson('/api/admin/reports/dashboard');
        $dashboardRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['total_gears', 'total_bookings', 'total_revenue']]);

        $popularRes = $this->getJson('/api/admin/reports/popular-gear');
        $popularRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $lowStockRes = $this->getJson('/api/admin/reports/low-stock');
        $lowStockRes->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
