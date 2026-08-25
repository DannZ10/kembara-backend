<?php

namespace App\Services;

use App\Enums\DeliveryType;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class DeliveryFeeService
{
    /** Fallbacks used when a setting row is missing (matches SettingsSeeder). */
    private const DEFAULTS = [
        'delivery_base_fee' => 10000,
        'delivery_free_radius_km' => 5,
        'delivery_free_weight_kg' => 5,
        'delivery_per_km_fee' => 1000,
        'delivery_per_kg_fee' => 1000,
        'basecamp_lat' => -7.9547684,
        'basecamp_lng' => 112.6087311,
    ];

    private function settings(): array
    {
        $out = [];
        foreach (self::DEFAULTS as $key => $default) {
            $out[$key] = (float) Setting::get($key, $default);
        }

        return $out;
    }

    /**
     * Base fee covers deliveries within the free radius AND under the free
     * weight. Each started km beyond the radius, or kg beyond the weight, adds
     * its per-unit fee.
     */
    public function calculateFee(DeliveryType|string $deliveryType, ?float $distanceKm = null, float $weightKg = 0): float
    {
        $type = $deliveryType instanceof DeliveryType ? $deliveryType->value : $deliveryType;
        if ($type === DeliveryType::PICKUP->value) {
            return 0.0;
        }

        $s = $this->settings();
        $distanceKm = max(0.0, (float) $distanceKm);
        $weightKg = max(0.0, $weightKg);

        $extraKm = max(0, (int) ceil($distanceKm - $s['delivery_free_radius_km']));
        $extraKg = max(0, (int) ceil($weightKg - $s['delivery_free_weight_kg']));

        return $s['delivery_base_fee'] + ($extraKm * $s['delivery_per_km_fee']) + ($extraKg * $s['delivery_per_kg_fee']);
    }

    /** Straight-line ("radius") distance in km from basecamp to a Google Maps link. */
    public function resolveDistanceKm(?string $mapsUrl): ?float
    {
        if (! $mapsUrl) {
            return null;
        }

        $coords = $this->extractCoords($mapsUrl);
        if (! $coords) {
            return null;
        }

        $s = $this->settings();

        return round($this->haversine($s['basecamp_lat'], $s['basecamp_lng'], $coords[0], $coords[1]), 2);
    }

    /** {distance_km, weight_kg, delivery_fee} for a live checkout preview. */
    public function quote(string $deliveryType, ?string $mapsUrl, float $weightKg): array
    {
        $distanceKm = $deliveryType === DeliveryType::DELIVERY->value
            ? $this->resolveDistanceKm($mapsUrl)
            : null;

        return [
            'distance_km' => $distanceKm,
            'weight_kg' => round($weightKg, 2),
            'delivery_fee' => $this->calculateFee($deliveryType, $distanceKm, $weightKg),
        ];
    }

    /** Pull [lat, lng] out of a Google Maps URL, resolving short links first. */
    private function extractCoords(string $url): ?array
    {
        $url = trim($url);
        $host = parse_url($url, PHP_URL_HOST) ?: '';

        // Only resolve redirects for genuine Google short-link hosts (exact match
        // or a real *.goo.gl subdomain) — never a host that merely CONTAINS
        // "goo.gl" (e.g. goo.gl.evil.com), which would be an SSRF pivot to an
        // attacker-controlled or internal host.
        $isGoogleShortLink = in_array($host, ['goo.gl', 'g.co', 'maps.app.goo.gl'], true)
            || str_ends_with($host, '.goo.gl');
        if ($isGoogleShortLink) {
            $url = $this->followRedirect($url) ?? $url;
        }

        // Place marker (!3d<lat>!4d<lng>) is the most reliable, then map center
        // (@<lat>,<lng>), then query params (q=/query=/ll=/destination=/daddr=).
        $patterns = [
            '/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/',
            '/@(-?\d+\.\d+),(-?\d+\.\d+)/',
            '/[?&](?:q|query|ll|destination|daddr)=(-?\d+\.\d+),\s*(-?\d+\.\d+)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $m)) {
                return [(float) $m[1], (float) $m[2]];
            }
        }

        return null;
    }

    private function followRedirect(string $url): ?string
    {
        try {
            $res = Http::withOptions(['allow_redirects' => false])
                ->timeout(8)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->get($url);

            return $res->header('Location') ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthKm * 2 * asin(min(1.0, sqrt($a)));
    }
}
