<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        
        // 👇 INI KUNCI UTAMANYA! (Jangan dihapus)
        api: __DIR__.'/../routes/api.php', 
        
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // CSRF exceptions — Midtrans webhook + portal siswa callback
        $middleware->validateCsrfTokens(except: [
            'midtrans-callback',
            'api/midtrans-callback',
            'midtrans/webhook',
            'siswa/payment/callback',        // Midtrans server-to-server callback
            'siswa/payment/flip-callback',   // Flip Accept Payment V2 server-to-server callback (token-authenticated in controller)
        ]);

        // Middleware alias
        $middleware->alias([
            'auth.siswa' => \App\Http\Middleware\AuthSiswa::class,
            'role'       => \App\Http\Middleware\EnsureRole::class,
        ]);

        // Phase E.2-CLOSE (LOCAL DEV ONLY, non-production): observe inbound Flip
        // callback requests to prove real Flip origin. Logs only SAFE metadata
        // (method, ip, user-agent, host, presence of fields) — never the token
        // value, secrets, or full payload.
        // Phase E.2B — TEMPORARY local-only Flip callback observation (REMOVE AFTER E2E).
        if (env('APP_ENV') === 'local') {
            $middleware->append(\App\Http\Middleware\LogFlipCallbackInbound::class);
        }

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();