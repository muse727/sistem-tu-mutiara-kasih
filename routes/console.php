<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Phase 09D.2 — dispatch pending/retryable integration outbox events.
// Uses the Null/local transport (09D.3 will inject HMAC-HTTPS). No network
// call leaves TU in this phase.
Artisan::command('integration:dispatch {--limit=50}', function () {
    $dispatcher = new \App\Services\Integration\OutboxDispatcher(
        app(\App\Services\Integration\IntegrationTransport::class)
    );
    $result = $dispatcher->dispatch((int) $this->option('limit'));
    $this->info(sprintf(
        'claimed=%d delivered=%d retryable=%d permanent=%d',
        $result['claimed'], $result['delivered'], $result['retryable'], $result['permanent']
    ));
})->purpose('Dispatch TU integration outbox events (Phase 09D.2)');
