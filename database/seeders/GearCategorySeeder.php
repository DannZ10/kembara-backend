<?php

namespace Database\Seeders;

use App\Models\GearCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GearCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Tenda', 'description' => 'Tenda dome, tunnel, & ultralight berbagai kapasitas'],
            ['name' => 'Carrier/Tas', 'description' => 'Tas gunung 30L-80L ergonomis'],
            ['name' => 'Sleeping Bag', 'description' => 'SB suhu dingin, bulu angsa, & sintetis'],
            ['name' => 'Matras', 'description' => 'Matras foam lipat, alumunium, & inflatable air pad'],
            ['name' => 'Trekking Pole', 'description' => 'Tongkat pendaki bahan alumunium & carbon fiber'],
            ['name' => 'Sepatu & Sandal', 'description' => 'Sepatu waterproof & sandal gunung anti slip'],
            ['name' => 'Pakaian Outdoor', 'description' => 'Celana quick dry, kaos baselayer, & balaclava'],
            ['name' => 'Jaket & Raincoat', 'description' => 'Jaket waterproof, windbreaker, & jas hujan'],
            ['name' => 'Peralatan Masak', 'description' => 'Kompor portable, nesting, misting, & cooking set'],
            ['name' => 'Lampu & Headlamp', 'description' => 'Headlamp LED, senter, & lampu tenda rechargerable'],
            ['name' => 'Navigasi & GPS', 'description' => 'Kompas bidik, GPS handheld, & jam kompas'],
            ['name' => 'Hammock', 'description' => 'Hammock single, double, & flysheet tarp'],
            ['name' => 'Aksesoris', 'description' => 'Pisau lipat, carabiner, botol minum, & prybar'],
            ['name' => 'Perlengkapan Safety', 'description' => 'P3K kit, peluit darurat, thermal blanket, & survivor gear'],
        ];

        foreach ($categories as $cat) {
            GearCategory::updateOrCreate(
                ['name' => $cat['name']],
                [
                    'slug' => Str::slug($cat['name']),
                    'description' => $cat['description'],
                    'image_url' => 'https://images.unsplash.com/photo-1504280390367-361c6d9f38f4?w=500&auto=format&fit=crop&q=80',
                ]
            );
        }
    }
}
