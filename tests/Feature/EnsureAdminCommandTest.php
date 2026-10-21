<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EnsureAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_admin_account_when_missing(): void
    {
        $this->artisan('kembara:ensure-admin', [
            '--email' => 'admin@kembara.id',
            '--password' => 'Admin123',
        ])->assertSuccessful();

        $user = User::where('email', 'admin@kembara.id')->firstOrFail();

        $this->assertSame(UserRole::ADMIN, $user->role);
        $this->assertTrue(Hash::check('Admin123', $user->password));
    }

    public function test_it_is_idempotent_and_keeps_the_existing_password(): void
    {
        $this->artisan('kembara:ensure-admin', [
            '--email' => 'admin@kembara.id',
            '--password' => 'Admin123',
        ])->assertSuccessful();

        $this->artisan('kembara:ensure-admin', [
            '--email' => 'admin@kembara.id',
            '--password' => 'SomethingElse9',
        ])->assertSuccessful();

        $this->assertSame(1, User::where('email', 'admin@kembara.id')->count());
        $this->assertTrue(Hash::check('Admin123', User::where('email', 'admin@kembara.id')->first()->password));
    }

    public function test_it_promotes_an_existing_customer_and_can_reset_the_password(): void
    {
        $customer = new User(['name' => 'Budi', 'email' => 'admin@kembara.id', 'password' => 'old-password']);
        $customer->role = UserRole::CUSTOMER;
        $customer->save();

        $this->artisan('kembara:ensure-admin', [
            '--email' => 'admin@kembara.id',
            '--password' => 'Admin123',
            '--reset-password' => true,
        ])->assertSuccessful();

        $customer->refresh();

        $this->assertSame(UserRole::ADMIN, $customer->role);
        $this->assertTrue(Hash::check('Admin123', $customer->password));
    }

    public function test_it_skips_quietly_when_credentials_are_not_configured(): void
    {
        $this->artisan('kembara:ensure-admin')->assertSuccessful();

        $this->assertDatabaseCount('users', 0);
    }
}
