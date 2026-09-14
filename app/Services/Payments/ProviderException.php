<?php

namespace App\Services\Payments;

/**
 * Phase E.1 — Provider transport/auth/validation failure.
 * Distinct from business-rule RuntimeException so callers can map to HTTP.
 */
class ProviderException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $providerCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
