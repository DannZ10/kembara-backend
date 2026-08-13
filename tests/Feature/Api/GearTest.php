<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Gear;
use App\Models\GearCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GearTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_can_list_gears_with_pagination(): void
    {
        $response = $this->getJson('/api/gears?limit=5');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.per_page', 5);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_can_filter_gears_by_search_keyword(): void
    {
        $response = $this->getJson('/api/gears?search=Tenda');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        foreach ($response->json('data') as $item) {
            $match = stripos($item['name'], 'Tenda') !== false ||
                stripos($item['description'] ?? '', 'Tenda') !== false ||
                stripos($item['brand'] ?? '', 'Tenda') !== false;
            $this->assertTrue($match);
        }
    }

    public function test_can_show_gear_detail_by_id_and_slug(): void
    {
        $gear = Gear::first();

        $responseId = $this->getJson("/api/gears/{$gear->id}");
        $responseId->assertStatus(200)
            ->assertJsonPath('data.id', $gear->id);

        $responseSlug = $this->getJson("/api/gears/slug/{$gear->slug}");
        $responseSlug->assertStatus(200)
            ->assertJsonPath('data.slug', $gear->slug);
    }

    public function test_admin_can_create_new_gear(): void
    {
        $admin = User::where('role', UserRole::ADMIN)->first();
        $token = $admin->createToken('admin_token')->plainTextToken;
        $category = GearCategory::first();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/gears', [
                'category_id' => $category->id,
                'name' => 'Tenda Ultralight Pro 1P',
                'brand' => 'Naturehike',
                'price_per_day' => 50000,
                'stock_total' => 5,
                'description' => 'Tenda 1 person super ringan',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Tenda Ultralight Pro 1P');

        $this->assertDatabaseHas('gears', [
            'name' => 'Tenda Ultralight Pro 1P',
            'stock_available' => 5,
        ]);
    }

    public function test_customer_cannot_create_gear(): void
    {
        $customer = User::where('role', UserRole::CUSTOMER)->first();
        $token = $customer->createToken('customer_token')->plainTextToken;
        $category = GearCategory::first();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/gears', [
                'category_id' => $category->id,
                'name' => 'Tenda Hacking Attempt',
                'price_per_day' => 1000,
                'stock_total' => 1,
            ]);

        $response->assertStatus(403);
    }
}
