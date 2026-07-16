<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Support\IpRateLimiter;
use App\Support\SecurityLog;
use Illuminate\Http\JsonResponse;

/**
 * Shared per-IP throttle for the AJAX auth controllers (forgot / reset password),
 * which both answer with a JSON 429 and a security-log entry. Returns the 429
 * response when the IP is over its cap, or null to let the request proceed.
 */
trait ThrottlesByIp
{
    protected function throttledJsonResponse(
        string $key,
        int $maxAttempts,
        string $event,
        string $logMessage,
        string $userMessage
    ): ?JsonResponse {
        if (! IpRateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return null;
        }

        SecurityLog::warning($event, $logMessage, [
            'email' => request()->input('email'),
            'retry_after' => IpRateLimiter::availableIn($key),
        ]);

        return response()->json([
            'success' => false,
            'message' => $userMessage,
        ], 429);
    }
}
