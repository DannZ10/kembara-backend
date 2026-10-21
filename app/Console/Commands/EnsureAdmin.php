<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Idempotently provision the admin account from environment variables, so an
 * admin can be created on a PaaS where no shell is available (e.g. Render free
 * plan). Safe to run on every boot:
 *  - missing account  -> created with role=admin
 *  - existing account -> promoted to admin if needed; its password is left
 *    untouched unless --reset-password is passed
 */
class EnsureAdmin extends Command
{
    protected $signature = 'kembara:ensure-admin
                            {--email= : Admin email (defaults to ADMIN_EMAIL)}
                            {--password= : Admin password (defaults to ADMIN_PASSWORD)}
                            {--name= : Display name (defaults to ADMIN_NAME)}
                            {--reset-password : Overwrite the password of an existing account}';

    protected $description = 'Create the admin account if missing, or promote an existing one (idempotent)';

    public function handle(): int
    {
        // env() is fine here: the entrypoint never caches config (it runs
        // config:clear), so the platform-injected variables stay readable.
        $email = $this->option('email') ?: env('ADMIN_EMAIL');
        $password = $this->option('password') ?: env('ADMIN_PASSWORD');
        $name = $this->option('name') ?: env('ADMIN_NAME') ?: 'Admin Kembara.id';

        if (! $email || ! $password) {
            $this->warn('ADMIN_EMAIL / ADMIN_PASSWORD not set; skipping admin provisioning.');

            return self::SUCCESS;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid admin email: {$email}");

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            // role is not mass-assignable (guarded); the model's 'hashed' cast
            // takes care of hashing the plain password.
            $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
            $user->role = UserRole::ADMIN;
            $user->save();

            $this->info("Admin created: {$email}");

            return self::SUCCESS;
        }

        $changes = [];

        if ($user->role !== UserRole::ADMIN) {
            $user->role = UserRole::ADMIN;
            $changes[] = 'promoted to admin';
        }

        if ($this->option('reset-password')) {
            $user->password = $password;
            $changes[] = 'password reset';
        }

        if ($changes === []) {
            $this->info("Admin already present, nothing to do: {$email}");

            return self::SUCCESS;
        }

        $user->save();
        $this->info("Admin updated ({$email}): ".implode(', ', $changes));

        return self::SUCCESS;
    }
}
