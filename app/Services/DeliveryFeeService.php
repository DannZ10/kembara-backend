<?php

namespace App\Services;

use App\Enums\DeliveryType;

class DeliveryFeeService
{
    public function calculateFee(DeliveryType|string $deliveryType, ?float $distanceKm = null): float
    {
        $typeValue = $deliveryType instanceof DeliveryType ? $deliveryType->value : $deliveryType;

        if ($typeValue === DeliveryType::PICKUP->value || ! $distanceKm || $distanceKm <= 0) {
            return 0.0;
        }

        $baseFee = (float) config('gearnest.delivery.base_fee', 10000);
        $perKmFee = (float) config('gearnest.delivery.per_km_fee', 3000);

        return $baseFee + ($distanceKm * $perKmFee);
    }
}
