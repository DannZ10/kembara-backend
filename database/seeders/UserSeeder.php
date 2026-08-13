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
        User::updateOrCreate(
            ['email' => 'admin@gearnest.com'],
            [
                'name' => 'Admin GearNest',
                'password' => Hash::make('admin123'),
                'phone' => '081234567890',
                'address' => 'Jl. Outdoor No. 1, Jakarta Central',
                'role' => UserRole::ADMIN,
            ]
        );

        User::updateOrCreate(
            ['email' => 'customer@gearnest.com'],
            [
                'name' => 'Andi Outdoor',
                'password' => Hash::make('customer123'),
                'phone' => '089876543210',
                'address' => 'Jl. Pendaki No. 88, Bandung',
                'role' => UserRole::CUSTOMER,
            ]
        );
    }
}
