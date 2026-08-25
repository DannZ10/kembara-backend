<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->defineRateLimiters();
        $this->enforceTokenIdleTimeout();
    }

    /**
     * Named rate limiters. Each name is its own bucket — inline `throttle:x,y`
     * middlewares are all unnamed and share one cache key per IP, so a strict
     * login limit would be drained by ordinary catalog traffic. Named limiters
     * keep the login/register bucket isolated from general API usage.
     */
    private function defineRateLimiters(): void
    {
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
        RateLimiter::for('google', fn (Request $r) => Limit::perMinute(30)->by($r->ip()));
        RateLimiter::for('delivery', fn (Request $r) => Limit::perMinute(60)->by($r->ip()));
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
