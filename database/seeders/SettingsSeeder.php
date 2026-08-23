<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        // firstOrCreate: never overwrite values an admin has already tuned.
        $defaults = [
            'delivery_base_fee' => 10000,
            'delivery_free_radius_km' => 5,
            'delivery_free_weight_kg' => 5,
            'delivery_per_km_fee' => 1000,
            'delivery_per_kg_fee' => 1000,
            'basecamp_lat' => -7.9547684,   // Basecamp Kembara.id — Jl. Sumbersari, Lowokwaru, Malang
            'basecamp_lng' => 112.6087311,
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => (string) $value]);
        }
    }
}
