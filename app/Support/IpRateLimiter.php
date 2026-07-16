<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Shared per-IP / per-identity throttle helper.
 *
 * Owns rate-limiter key construction so the format lives in one place, and hashes
 * any user-supplied component (e.g. an email) into the key. Laravel's RateLimiter
 * runs htmlentities()+entity-stripping over raw keys, which collapses distinct
 * RFC-valid emails onto the same bucket (josé@x.com vs jose@x.com); hashing the
 * identity first removes that collision.
 */
class IpRateLimiter
{
    /**
     * Build a key scoped to a single IP: e.g. "password-email:1.2.3.4".
     */
    public static function ipKey(string $prefix, ?string $ip): string
    {
        return $prefix.':'.$ip;
    }

    /**
     * Build a key scoped to an identity (email) + IP, with the identity hashed so
     * special characters cannot alias one account's bucket onto another's.
     */
    public static function identityKey(string $prefix, string $identity, ?string $ip): string
    {
        return $prefix.':'.hash('sha256', $identity).'|'.$ip;
    }

    public static function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return RateLimiter::tooManyAttempts($key, $maxAttempts);
    }

    public static function hit(string $key, int $decaySeconds): void
    {
        RateLimiter::hit($key, $decaySeconds);
    }

    public static function availableIn(string $key): int
    {
        return RateLimiter::availableIn($key);
    }

    public static function clear(string $key): void
    {
        RateLimiter::clear($key);
    }
}
