<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        $this->configureRateLimiting();
    }

    /**
     * Rate limits, applied by ObservableThrottleRequests (the `throttle` alias).
     *
     * Every throttle runs BEFORE auth.token.store - the framework sorts it ahead
     * of the api group's SubstituteBindings - so a throttled request never costs
     * a pizzasys round-trip. That also means no user id exists yet: limits are
     * keyed on a hash of the bearer token, or the IP when there is none.
     *
     * Phase 1: the real limits are generous and the `target:` limits are the
     * numbers we expect to end on - they only log when passed. Phase 2 sets the
     * real limits from those logs and drops the targets.
     */
    private function configureRateLimiting(): void
    {
        // Every route: per token, reads and writes counted apart, plus a
        // per-IP ceiling for floods of made-up tokens (a store's staff share
        // one NAT address, hence the size).
        RateLimiter::for('api', function (Request $request) {
            $token = self::tokenKey($request);
            $kind = $request->isMethodSafe() ? 'r:' : 'w:';
            $ip = 'ip:'.$request->ip();

            return [
                Limit::perMinute($request->isMethodSafe() ? 600 : 300)->by($kind.$token),
                Limit::perMinute(5000)->by($ip),
                Limit::perMinute($request->isMethodSafe() ? 120 : 60)->by('target:'.$kind.$token),
                Limit::perMinute(1200)->by('target:'.$ip),
            ];
        });

        // The bell and announcement pollers, and the websocket subscribe.
        // Poll routes drop `throttle:api` (withoutMiddleware) - its read limit
        // would cap them - so they carry the per-IP ceiling themselves. Keyed
        // apart from tokenKey(), which is also `ip:` when there is no bearer.
        RateLimiter::for('api-poll', fn (Request $request) => [
            Limit::perMinute(1200)->by(self::tokenKey($request)),
            Limit::perMinute(5000)->by('ceiling:ip:'.$request->ip()),
            Limit::perMinute(300)->by('target:'.self::tokenKey($request)),
        ]);
    }

    /**
     * The caller's bearer token, hashed - never stored or logged raw - or the
     * IP when there is no token.
     */
    private static function tokenKey(Request $request): string
    {
        $token = (string) $request->bearerToken();

        return $token !== '' ? 't:'.hash('sha256', $token) : 'ip:'.$request->ip();
    }
}
