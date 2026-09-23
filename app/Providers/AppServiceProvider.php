<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('admin-login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('client-login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('client-registration', fn (Request $request) => Limit::perMinute(10)
            ->by($request->ip()));

        RateLimiter::for('license-activation', fn (Request $request) => Limit::perMinute(5)
            ->by('client:'.($request->user()?->getKey() ?? $request->ip())));

        RateLimiter::for('facebook-auth', fn (Request $request) => Limit::perMinute(20)
            ->by($request->ip()));
    }
}
