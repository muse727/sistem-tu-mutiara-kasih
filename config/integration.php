<?php

/**
 * Phase 09D.3 — TU → Finance integration transport configuration.
 *
 * Secrets come from the environment and are NEVER committed. Local/dev/test
 * uses a dedicated non-production secret. No production URL/credential here.
 */
return [
    /*
     * Phase 09D.4 — forward-only SPP integration activation gate.
     * Enabled: whether integration delivery is active at all. When disabled,
     * SPP payments still succeed; no outbox event is created and nothing is
     * delivered. Disabling never marks a valid payment as failed.
     * Activated-at: only SPP payments settled (paid_at) AT/AFTER this ISO-8601
     * timestamp are eligible. Source settlement time, not dispatch time.
     * No historical backfill: pre-activation paid bills are never integrated.
     */
    'enabled'       => (bool) env('TU_FINANCE_INTEGRATION_ENABLED', false),
    'activated_at'  => env('TU_FINANCE_INTEGRATION_ACTIVATED_AT'), // ISO-8601 +07:00

    'finance' => [
        // Finance ingest endpoint (local dev only in this phase; never production).
        'url'     => env('TU_FINANCE_URL'),           // e.g. http://localhost:8000/api/integration/tu/events
        'key_id'  => env('TU_HMAC_KEY_ID', 'tu-local'),
        'secret'  => env('TU_HMAC_SECRET'),           // shared with Finance; env-only
        'timeout' => (int) env('TU_FINANCE_TIMEOUT', 10),
    ],
];
