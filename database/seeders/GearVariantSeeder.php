<?php

namespace Database\Seeders;

use App\Models\Gear;
use Illuminate\Database\Seeder;

class GearVariantSeeder extends Seeder
{
    public function run(): void
    {
        // Only a few gears carry variations (apparel/footwear). Everything else
        // keeps tracking stock on the gear itself.
        $map = [
            'Eiger Waterproof Jacket Gore-Tex' => [
                ['size' => 'M', 'color' => 'Hitam', 'stock' => 2],
                ['size' => 'L', 'color' => 'Hitam', 'stock' => 3],
                ['size' => 'L', 'color' => 'Merah', 'stock' => 1],
                ['size' => 'XL', 'color' => 'Olive', 'stock' => 2],
            ],
            'Eiger Anaconda Waterproof Hiking Shoes' => [
                ['size' => '40', 'color' => null, 'stock' => 2],
                ['size' => '42', 'color' => null, 'stock' => 3],
                ['size' => '44', 'color' => null, 'stock' => 3],
            ],
            'Consina Mustang Trail Shoes' => [
                ['size' => '39', 'color' => null, 'stock' => 2],
                ['size' => '41', 'color' => null, 'stock' => 4],
                ['size' => '43', 'color' => null, 'stock' => 4],
            ],
        ];

        foreach ($map as $gearName => $variants) {
            $gear = Gear::where('name', $gearName)->first();
            if (! $gear) {
                continue;
            }

            // Re-runnable: reset this gear's variants, then recreate.
            $gear->variants()->delete();
            $gear->variants()->createMany($variants);

            // Keep the gear's displayed stock coherent with its variant total.
            $total = array_sum(array_column($variants, 'stock'));
            $gear->update(['stock_total' => $total, 'stock_available' => $total]);
        }
    }
}
