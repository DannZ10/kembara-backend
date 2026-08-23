<?php

namespace Tests\Feature\Api;

use App\Services\DeliveryFeeService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryFeeTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryFeeService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        $this->svc = app(DeliveryFeeService::class);
    }

    public function test_pickup_is_free(): void
    {
        $this->assertSame(0.0, $this->svc->calculateFee('pickup', 20, 20));
    }

    public function test_within_free_radius_and_weight_is_base_fee(): void
    {
        // < 5km AND < 5kg -> flat base fee.
        $this->assertSame(10000.0, $this->svc->calculateFee('delivery', 3, 3));
    }

    public function test_each_started_km_and_kg_over_threshold_adds_a_thousand(): void
    {
        // 7km -> ceil(2)=2 extra km; 8kg -> ceil(3)=3 extra kg. 10000 + 2000 + 3000.
        $this->assertSame(15000.0, $this->svc->calculateFee('delivery', 7, 8));

        // 7.5km -> ceil(2.5)=3; 9kg -> ceil(4)=4. 10000 + 3000 + 4000.
        $this->assertSame(17000.0, $this->svc->calculateFee('delivery', 7.5, 9));
    }

    public function test_resolves_distance_from_maps_place_url(): void
    {
        // Basecamp coordinate itself -> ~0 km.
        $atBasecamp = 'https://www.google.com/maps/place/Basecamp/@-7.9547684,112.6061562,863m/data=!3d-7.9547684!4d112.6087311';
        $this->assertNotNull($this->svc->resolveDistanceKm($atBasecamp));
        $this->assertLessThan(0.5, $this->svc->resolveDistanceKm($atBasecamp));

        // Roughly 1 degree of latitude away (~111 km).
        $farAway = 'https://www.google.com/maps/place/Far/data=!3d-6.9547684!4d112.6087311';
        $this->assertGreaterThan(100, $this->svc->resolveDistanceKm($farAway));
    }

    public function test_unparseable_link_returns_null(): void
    {
        $this->assertNull($this->svc->resolveDistanceKm('https://www.google.com/maps/place/Some+Place+Name'));
    }
}
