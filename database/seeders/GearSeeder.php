<?php

namespace Database\Seeders;

use App\Models\Gear;
use App\Models\GearCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GearSeeder extends Seeder
{
    public function run(): void
    {
        $cats = GearCategory::pluck('id', 'name');

        $gears = [
            // Tenda
            ['name' => 'Naturehike Cloud Up 2 Upgrade', 'category' => 'Tenda', 'brand' => 'Naturehike', 'price' => 60000, 'stock' => 8, 'weight' => 1.7, 'desc' => 'Tenda 2 orang ultralight waterproof 4000mm dengan footprint.'],
            ['name' => 'Eiger Shira 2P Tent', 'category' => 'Tenda', 'brand' => 'Eiger', 'price' => 75000, 'stock' => 6, 'weight' => 2.3, 'desc' => 'Tenda dome 2 person alloy pole tangguh badai.'],
            ['name' => 'Consina Magma 4 Person', 'category' => 'Tenda', 'brand' => 'Consina', 'price' => 85000, 'stock' => 5, 'weight' => 3.8, 'desc' => 'Tenda kapasitas 4 orang dengan vestibule luas.'],
            ['name' => 'Mobo Outdoor Dome 4P', 'category' => 'Tenda', 'brand' => 'Mobo', 'price' => 70000, 'stock' => 7, 'weight' => 3.5, 'desc' => 'Tenda keluarga 4 orang frame fiberglass kuat.'],

            // Carrier
            ['name' => 'Deuter Aircontact Lite 65+10', 'category' => 'Carrier/Tas', 'brand' => 'Deuter', 'price' => 70000, 'stock' => 6, 'weight' => 1.9, 'desc' => 'Carrier premium 75L backsystem sangat nyaman saat beban berat.'],
            ['name' => 'Osprey Atmos AG 50', 'category' => 'Carrier/Tas', 'brand' => 'Osprey', 'price' => 80000, 'stock' => 4, 'weight' => 2.0, 'desc' => 'Carrier anti-gravity suspension mesh ventilated backsystem.'],
            ['name' => 'Eiger Rhinos 60L Backpack', 'category' => 'Carrier/Tas', 'brand' => 'Eiger', 'price' => 55000, 'stock' => 10, 'weight' => 1.8, 'desc' => 'Tas gunung favorit pendaki lokal termasuk raincover.'],
            ['name' => 'Consina Tarebbi 60L', 'category' => 'Carrier/Tas', 'brand' => 'Consina', 'price' => 45000, 'stock' => 12, 'weight' => 1.6, 'desc' => 'Carrier ekonomis 60L busa tebal & jahitan kompresi.'],

            // Sleeping Bag
            ['name' => 'Consina Sleeping Bag Extreme -5°C', 'category' => 'Sleeping Bag', 'brand' => 'Consina', 'price' => 30000, 'stock' => 15, 'weight' => 1.1, 'desc' => 'SB mummy polar tebal nyaman untuk suhu dingin puncak.'],
            ['name' => 'Naturehike Goose Down SB 800G', 'category' => 'Sleeping Bag', 'brand' => 'Naturehike', 'price' => 45000, 'stock' => 8, 'weight' => 0.9, 'desc' => 'SB bulu angsa hangat dan ultra-compact saat dilipat.'],
            ['name' => 'Eiger Thermal SB -2C', 'category' => 'Sleeping Bag', 'brand' => 'Eiger', 'price' => 35000, 'stock' => 10, 'weight' => 1.2, 'desc' => 'SB dacron serat hollow fiber tahan angin.'],

            // Matras
            ['name' => 'Naturehike Inflatable Air Matras dengan Pompa', 'category' => 'Matras', 'brand' => 'Naturehike', 'price' => 25000, 'stock' => 12, 'weight' => 0.6, 'desc' => 'Matras angin empuk lengkap built-in sponge pump.'],
            ['name' => 'Matras Alumunium Foil Double Side', 'category' => 'Matras', 'brand' => 'Dhaulagiri', 'price' => 15000, 'stock' => 20, 'weight' => 0.3, 'desc' => 'Matras foil pemantul panas tubuh tahan lembab tanah.'],
            ['name' => 'Matras Foam Lipat Egg Crate', 'category' => 'Matras', 'brand' => 'Klymit', 'price' => 20000, 'stock' => 15, 'weight' => 0.4, 'desc' => 'Matras busa telur empuk tidak bocor.'],

            // Trekking Pole
            ['name' => 'Naturehike Carbon Fiber Trekking Pole Pair', 'category' => 'Trekking Pole', 'brand' => 'Naturehike', 'price' => 20000, 'stock' => 14, 'weight' => 0.4, 'desc' => 'Sepasang tongkat pendaki super ringan carbon fiber 3 section.'],
            ['name' => 'Dhaulagiri Anti-shock Alloy Pole', 'category' => 'Trekking Pole', 'brand' => 'Dhaulagiri', 'price' => 12000, 'stock' => 20, 'weight' => 0.3, 'desc' => 'Trekking pole aluminium alloy dengan fitur per anti-shock.'],

            // Sepatu & Sandal
            ['name' => 'Eiger Anaconda Waterproof Hiking Shoes', 'category' => 'Sepatu & Sandal', 'brand' => 'Eiger', 'price' => 50000, 'stock' => 8, 'weight' => 1.2, 'desc' => 'Sepatu gunung mid-cut membran waterproof grip kuat.'],
            ['name' => 'Consina Mustang Trail Shoes', 'category' => 'Sepatu & Sandal', 'brand' => 'Consina', 'price' => 40000, 'stock' => 10, 'weight' => 1.0, 'desc' => 'Sepatu trekking tahan gesekan batuan tajam.'],
            ['name' => 'Sandal Gunung Eiger Caldera', 'category' => 'Sepatu & Sandal', 'brand' => 'Eiger', 'price' => 20000, 'stock' => 15, 'weight' => 0.7, 'desc' => 'Sandal outdoor sol karet rubber grip tinggi.'],

            // Pakaian Outdoor
            ['name' => 'Celana Quick Dry Convertible Eiger', 'category' => 'Pakaian Outdoor', 'brand' => 'Eiger', 'price' => 25000, 'stock' => 10, 'weight' => 0.4, 'desc' => 'Celana sambung 2-in-1 cepat kering anti uv.'],
            ['name' => 'Kaos Baselayer Thermal Compression', 'category' => 'Pakaian Outdoor', 'brand' => 'Consina', 'price' => 15000, 'stock' => 15, 'weight' => 0.2, 'desc' => 'Kaos manset penahan dingin keringat cepat nguap.'],

            // Jaket & Raincoat
            ['name' => 'Eiger Waterproof Jacket Gore-Tex', 'category' => 'Jaket & Raincoat', 'brand' => 'Eiger', 'price' => 45000, 'stock' => 8, 'weight' => 0.6, 'desc' => 'Jaket gunung stormproof fully seamed sealed.'],
            ['name' => 'Jas Hujan Ponco Raincoat Kael', 'category' => 'Jaket & Raincoat', 'brand' => 'Consina', 'price' => 20000, 'stock' => 15, 'weight' => 0.5, 'desc' => 'Jas hujan ponco bisa dijadikan shelter flysheet.'],

            // Peralatan Masak
            ['name' => 'Kompor Portable Mawar Windproof', 'category' => 'Peralatan Masak', 'brand' => 'Kovar', 'price' => 20000, 'stock' => 15, 'weight' => 0.5, 'desc' => 'Kompor gas kaleng dengan pelindung angin kelopak mawar.'],
            ['name' => 'Cooking Set Nesting DS-308 (3-4 Orang)', 'category' => 'Peralatan Masak', 'brand' => 'Dhaulagiri', 'price' => 25000, 'stock' => 12, 'weight' => 0.8, 'desc' => 'Panci nesting anoda alumunium lengkap penggorengan & teko.'],
            ['name' => 'Kompor Ultralight Canister Stove 45g', 'category' => 'Peralatan Masak', 'brand' => 'Fire-Maple', 'price' => 18000, 'stock' => 10, 'weight' => 0.1, 'desc' => 'Kompor mini ultralight nyala api terfokus.'],

            // Lampu & Headlamp
            ['name' => 'Headlamp Naturehike Rechargerable 220 Lumen', 'category' => 'Lampu & Headlamp', 'brand' => 'Naturehike', 'price' => 15000, 'stock' => 15, 'weight' => 0.1, 'desc' => 'Senter kepala LED baterai charger USB waterproof.'],
            ['name' => 'Lampu Tenda LED Solar Powerbank', 'category' => 'Lampu & Headlamp', 'brand' => 'Dhaulagiri', 'price' => 15000, 'stock' => 12, 'weight' => 0.3, 'desc' => 'Lampu gantung tent lampu lentera bertenaga surya.'],

            // Navigasi & GPS
            ['name' => 'Kompas Bidik Prismatik Brunton', 'category' => 'Navigasi & GPS', 'brand' => 'Brunton', 'price' => 15000, 'stock' => 10, 'weight' => 0.2, 'desc' => 'Kompas peta bidik sudut akurat.'],

            // Hammock
            ['name' => 'Hammock Single Parachute Fabric 270x150cm', 'category' => 'Hammock', 'brand' => 'Ticket To The Moon', 'price' => 20000, 'stock' => 15, 'weight' => 0.5, 'desc' => 'Hammock kain parasut parasilk elastis beban 200kg.'],
            ['name' => 'Flysheet Tarp Waterproof 3x4 Meter', 'category' => 'Hammock', 'brand' => 'Consina', 'price' => 20000, 'stock' => 18, 'weight' => 0.9, 'desc' => 'Atap peneduh tenda/hammock 19 loop webbing.'],

            // Aksesoris & Safety
            ['name' => 'Pisau Lipat Multi-tool Swiss Army Style', 'category' => 'Aksesoris', 'brand' => 'Victorinox', 'price' => 15000, 'stock' => 10, 'weight' => 0.2, 'desc' => 'Multitool 12 fungsi termasuk gunting & pembuka botol.'],
            ['name' => 'First Aid Kit Medical P3K Mini Pouch', 'category' => 'Perlengkapan Safety', 'brand' => 'OneMed', 'price' => 15000, 'stock' => 20, 'weight' => 0.3, 'desc' => 'Pouch P3K lengkap kasa, betadine, plester, & perban.'],
        ];

        foreach ($gears as $g) {
            $catId = $cats[$g['category']] ?? 1;
            Gear::updateOrCreate(
                ['name' => $g['name']],
                [
                    'category_id' => $catId,
                    'slug' => Str::slug($g['name']),
                    'description' => $g['desc'],
                    'brand' => $g['brand'],
                    'price_per_day' => $g['price'],
                    'stock_total' => $g['stock'],
                    'stock_available' => $g['stock'],
                    'image_url' => 'https://images.unsplash.com/photo-1504280390367-361c6d9f38f4?w=500&auto=format&fit=crop&q=80',
                    'weight_kg' => $g['weight'],
                    'is_available' => true,
                ]
            );
        }
    }
}
