<?php

namespace App\Providers;

use App\Services\Business\Modules;
use App\Services\Business\Platform;
use App\Services\Payments\PaymentModule;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(PaymentModule::class);
        $this->app->scoped(Modules::class);
        $this->app->scoped(Platform::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('bizpilot-register', fn ($request) => Limit::perMinute(5)->by('registration:'.$request->ip()));
        RateLimiter::for('bizpilot-login', function ($request) {
            $email = is_string($request->input('email')) ? mb_strtolower($request->input('email')) : '';

            return Limit::perMinute(5)->by(hash('sha256', $email.'|'.$request->ip()));
        });
    }
}
