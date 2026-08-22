<?php

namespace App\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->enforceTokenIdleTimeout();
    }

    /**
     * Reject (and prune) access tokens idle longer than the configured window,
     * giving admins/customers an automatic logout after inactivity.
     *
     * Runs during Sanctum auth resolution, before last_used_at is refreshed,
     * so it measures the gap since the *previous* request — a sliding window.
     */
    private function enforceTokenIdleTimeout(): void
    {
        $minutes = (int) config('sanctum.idle_timeout', 30);

        if ($minutes <= 0) {
            return;
        }

        Sanctum::authenticateAccessTokensUsing(function ($accessToken, bool $isValid) use ($minutes) {
            if (! $isValid) {
                return false;
            }

            $lastUsedAt = $accessToken->last_used_at;

            if ($lastUsedAt instanceof Carbon && $lastUsedAt->lt(now()->subMinutes($minutes))) {
                $accessToken->delete();

                return false;
            }

            return true;
        });
    }
}
