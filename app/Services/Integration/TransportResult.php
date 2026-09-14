<?php

namespace App\Services\Integration;

/**
 * Phase 09D.2 — deterministic result of a single delivery attempt.
 */
class TransportResult
{
    public const DELIVERED   = 'delivered';
    public const RETRYABLE   = 'retryable';    // network/timeout/5xx → retry later
    public const PERMANENT   = 'permanent';    // malformed/unsupported/invalid → do not retry

    private function __construct(
        public readonly string $outcome,
        public readonly ?string $error = null,
    ) {}

    public static function delivered(): self
    {
        return new self(self::DELIVERED);
    }

    public static function retryable(string $error): self
    {
        return new self(self::RETRYABLE, $error);
    }

    public static function permanent(string $error): self
    {
        return new self(self::PERMANENT, $error);
    }

    public function isDelivered(): bool
    {
        return $this->outcome === self::DELIVERED;
    }
}
