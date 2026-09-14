<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Phase 09D.3 — outbox transport boundary. The HMAC-HTTPS transport is
        // bound for delivery; it is a no-op (retryable 'not-configured') until
        // TU_FINANCE_URL + TU_HMAC_SECRET are set for local dev. It never calls
        // a network when unconfigured, and never logs secrets.
        $this->app->bind(
            \App\Services\Integration\IntegrationTransport::class,
            \App\Services\Integration\HmacIntegrationTransport::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
