<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // role is not mass-assignable (guarded), so set it after the fill.
        $admin = User::updateOrCreate(
            ['email' => 'admin@kembara.com'],
            [
                'name' => 'Admin Kembara.id',
                'password' => Hash::make('admin123'),
                'phone' => '081234567890',
                'address' => 'Jl. Outdoor No. 1, Jakarta Central',
            ]
        );
        $admin->role = UserRole::ADMIN;
        $admin->save();

        $customer = User::updateOrCreate(
            ['email' => 'customer@kembara.com'],
            [
                'name' => 'Andi Outdoor',
                'password' => Hash::make('customer123'),
                'phone' => '089876543210',
                'address' => 'Jl. Pendaki No. 88, Bandung',
            ]
        );
        $customer->role = UserRole::CUSTOMER;
        $customer->save();
    }
}
