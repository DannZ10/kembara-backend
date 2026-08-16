<?php

namespace Tests\Feature\Api;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Gear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_customer_can_create_pickup_booking(): void
    {
        $customer = User::where('role', UserRole::CUSTOMER)->first();
        $gear = Gear::first();
        $initialStock = $gear->stock_available;

        $token = $customer->createToken('customer_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/bookings', [
                'start_date' => now()->addDay()->format('Y-m-d'),
                'end_date' => now()->addDays(3)->format('Y-m-d'),
                'delivery_type' => 'pickup',
                'items' => [
                    [
                        'gear_id' => $gear->id,
                        'quantity' => 2,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.delivery_fee', '0.00')
            ->assertJsonPath('data.duration_days', 2);

        $this->assertDatabaseHas('gears', [
            'id' => $gear->id,
            'stock_available' => $initialStock - 2,
        ]);
    }

    public function test_booking_fails_when_stock_insufficient(): void
    {
        $customer = User::where('role', UserRole::CUSTOMER)->first();
        $gear = Gear::first();

        $token = $customer->createToken('customer_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/bookings', [
                'start_date' => now()->addDay()->format('Y-m-d'),
                'end_date' => now()->addDays(2)->format('Y-m-d'),
                'delivery_type' => 'pickup',
                'items' => [
                    [
                        'gear_id' => $gear->id,
                        'quantity' => 999,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_customer_cannot_view_other_customers_booking(): void
    {
        $customerA = User::factory()->create(['email' => 'customera@example.com', 'role' => UserRole::CUSTOMER]);
        $customerB = User::factory()->create(['email' => 'customerb@example.com', 'role' => UserRole::CUSTOMER]);

        $gear = Gear::first();

        // Create booking for Customer A
        Sanctum::actingAs($customerA);
        $createRes = $this->postJson('/api/bookings', [
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(2)->format('Y-m-d'),
            'delivery_type' => 'pickup',
            'items' => [
                ['gear_id' => $gear->id, 'quantity' => 1],
            ],
        ]);

        $bookingId = $createRes->json('data.id');

        // Customer B tries to view Customer A's booking
        Sanctum::actingAs($customerB);
        $viewRes = $this->getJson("/api/bookings/{$bookingId}");

        $viewRes->assertStatus(404);
    }

    public function test_cancelling_booking_restores_stock(): void
    {
        $customer = User::where('role', UserRole::CUSTOMER)->first();
        $admin = User::where('role', UserRole::ADMIN)->first();
        $gear = Gear::first();
        $initialStock = $gear->stock_available;

        // Booking 2 units
        Sanctum::actingAs($customer);
        $createRes = $this->postJson('/api/bookings', [
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(2)->format('Y-m-d'),
            'delivery_type' => 'pickup',
            'items' => [
                ['gear_id' => $gear->id, 'quantity' => 2],
            ],
        ]);

        $bookingId = $createRes->json('data.id');
        $this->assertEquals($initialStock - 2, $gear->fresh()->stock_available);

        // Admin cancels booking
        Sanctum::actingAs($admin);
        $cancelRes = $this->patchJson("/api/admin/bookings/{$bookingId}/status", [
            'status' => 'cancelled',
        ]);

        $cancelRes->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        // Stock restored!
        $this->assertEquals($initialStock, $gear->fresh()->stock_available);
    }

    public function test_admin_can_toggle_identity_verification_both_ways(): void
    {
        $customer = User::where('role', UserRole::CUSTOMER)->first();
        $admin = User::where('role', UserRole::ADMIN)->first();
        $gear = Gear::first();

        // Customer creates a booking (identity unverified by default)
        Sanctum::actingAs($customer);
        $createRes = $this->postJson('/api/bookings', [
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(2)->format('Y-m-d'),
            'delivery_type' => 'pickup',
            'items' => [
                ['gear_id' => $gear->id, 'quantity' => 1],
            ],
        ]);

        $bookingId = $createRes->json('data.id');
        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'identity_verified' => false,
        ]);

        Sanctum::actingAs($admin);

        // Verify identity
        $verifyRes = $this->patchJson("/api/admin/bookings/{$bookingId}/verify", [
            'verified' => true,
        ]);
        $verifyRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.identity_verified', true);
        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'identity_verified' => true,
        ]);

        // Un-verify identity (regression: this must actually set it back to false)
        $unverifyRes = $this->patchJson("/api/admin/bookings/{$bookingId}/verify", [
            'verified' => false,
        ]);
        $unverifyRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.identity_verified', false);
        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'identity_verified' => false,
        ]);
    }
}
