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

    /*
     * TU-FINANCE-BATCH-02 — SPP settlement batch mode.
     * When DISABLED (default): the existing per-bill spp.payment integration is
     * unchanged. When ENABLED: newly eligible SPP settlements become
     * batch-accounting candidates; per-bill spp.payment events are still
     * recorded to the outbox for trace but are NOT dispatched for Finance
     * accounting (only spp.settlement_batch events post). Forward-only:
     * spp_batch_activated_at is the explicit activation boundary — only SPP
     * payments settled (paid_at) AT/AFTER it become batch members, and
     * pre-boundary bills are never retroactively batched.
     */
    'spp_batch_enabled'      => (bool) env('TU_SPP_BATCH_ENABLED', false),
    'spp_batch_activated_at' => env('TU_SPP_BATCH_ACTIVATED_AT'), // ISO-8601 +07:00
    'spp_batch_period_kind'  => env('TU_SPP_BATCH_PERIOD_KIND', 'DAILY'), // DAILY | WEEKLY | MONTHLY

    'finance' => [
        // Finance ingest endpoint (local dev only in this phase; never production).
        'url'     => env('TU_FINANCE_URL'),           // e.g. http://localhost:8000/api/integration/tu/events
        'key_id'  => env('TU_HMAC_KEY_ID', 'tu-local'),
        'secret'  => env('TU_HMAC_SECRET'),           // shared with Finance; env-only
        'timeout' => (int) env('TU_FINANCE_TIMEOUT', 10),
    ],

    /*
     * TU-STUDENT-READ-CONTRACT — INBOUND HMAC verification (Finance → TU
     * read-only student endpoints). Map of allowed key IDs to shared secrets;
     * supports rotation exactly like the outbound scheme (add new key, switch
     * Finance, keep old until switched, remove old). Defaults to the same
     * env-provided shared secret as the outbound transport (TU_HMAC_KEY_ID /
     * TU_HMAC_SECRET); separate INBOUND vars allow key split if ever needed.
     * Secrets are env-only and NEVER committed.
     */
    'finance_hmac_keys' => array_filter([
        env('TU_HMAC_INBOUND_KEY_ID', env('TU_HMAC_KEY_ID', 'tu-local'))
            => env('TU_HMAC_INBOUND_SECRET', env('TU_HMAC_SECRET')),
    ]),
];
