<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase E.2B — TEMPORARY local-only observability (REMOVE AFTER E2E).
 *
 * Logs SAFE, non-secret callback correlation fields so a genuine Flip
 * callback can be matched to its transaction (id, bill_link_id, bill_link,
 * amount, status, created_at). The `token`/`secret`/`Authorization` are
 * NEVER logged — `token` is a sibling form field and is not read here.
 * Registered globally only when APP_ENV=local (see bootstrap/app.php).
 */
class LogFlipCallbackInbound
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('siswa/payment/flip-callback') && $request->isMethod('post')) {
            $data = is_string($request->input('data'))
                ? (array) json_decode($request->input('data'), true)
                : [];

            Log::info('FlipCallbackInbound', [
                'method'       => $request->method(),
                'ip'           => $request->ip(),
                'user_agent'   => substr((string) $request->userAgent(), 0, 120),
                'host'         => $request->getHost(),
                'cb_id'        => $data['id'] ?? null,
                'cb_link_id'   => $data['bill_link_id'] ?? ($data['link_id'] ?? null),
                'cb_bill_link' => $data['bill_link'] ?? null,
                'cb_amount'    => $data['amount'] ?? null,
                'cb_status'    => $data['status'] ?? null,
                'cb_created'   => $data['created_at'] ?? null,
            ]);
        }

        return $next($request);
    }
}
