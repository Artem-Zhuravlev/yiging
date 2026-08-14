<?php

declare(strict_types=1);

namespace App\AI;

interface RateLimiter
{
    /**
     * Checks and, if allowed, records one attempt for $key in a single call. Returns true
     * (and records it) when under the limit; returns false (recording nothing) when at or
     * over it.
     */
    public function attempt(string $key): bool;
}
