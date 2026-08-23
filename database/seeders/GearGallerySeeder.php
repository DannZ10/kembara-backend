<?php

namespace Database\Seeders;

use App\Models\Gear;
use Illuminate\Database\Seeder;

class GearGallerySeeder extends Seeder
{
    public function run(): void
    {
        $u = fn (string $id) => "https://images.unsplash.com/photo-{$id}?w=1000&q=80&auto=format&fit=crop";

        // Extra gallery images per gear (image_url stays the cover / first slide).
        $galleries = [
            'Naturehike Cloud Up 2 Upgrade' => [
                $u('1504280390367-361c6d9f38f4'),
                $u('1478131143081-80f7f84ca84d'),
                $u('1537565266759-34bbc16b62a9'),
            ],
            'Deuter Aircontact Lite 65+10' => [
                $u('1501555088652-021faa106b9b'),
                $u('1454496522488-7a8e488e8606'),
            ],
            'Eiger Waterproof Jacket Gore-Tex' => [
                $u('1523987355523-c7b5b0dd90a7'),
                $u('1522163182402-834f871fd851'),
            ],
        ];

        foreach ($galleries as $name => $images) {
            $gear = Gear::where('name', $name)->first();
            if ($gear) {
                $gear->images = $images; // array cast -> JSON on save
                $gear->save();
            }
        }
    }
}
