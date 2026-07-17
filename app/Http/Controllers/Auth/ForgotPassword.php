<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\ThrottlesByIp;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\IpRateLimiter;
use App\Support\SecurityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class ForgotPassword extends Controller
{
    use ThrottlesByIp;

    /**
     * Per-IP cap on reset emails, on top of the per-email broker throttle
     * (config/auth.php 'throttle' => 60). The broker throttle is per-email, so it
     * does nothing against one source requesting resets across many accounts —
     * mail-bombing / quota drain. This closes that gap. Resets are rare for real
     * users, so 5/hour is generous even on a shared club/family IP. Distributed
     * drains are surfaced by the warn-only mail monitor.
     */
    private const MAX_PER_IP = 5;

    private const DECAY_PER_IP = 3600; // seconds (1 hour)

    /**
     * Handle an incoming password reset link request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email:strict',
        ]);

        // Normalize so the lookup matches the lowercased stored email.
        $request->merge(['email' => User::normalizeEmail($request->email)]);

        // Per-IP throttle — gate before doing any work.
        $ipKey = IpRateLimiter::ipKey('password-email', $request->ip());
        if ($throttled = $this->throttledJsonResponse(
            $ipKey,
            self::MAX_PER_IP,
            'password_reset_throttled',
            'Password reset request throttled',
            'Too many reset requests. Please wait a while and try again.'
        )) {
            return $throttled;
        }

        // Send the password reset link
        $status = Password::sendResetLink(
            $request->only('email')
        );

        // Check if the password reset link was sent successfully
        if ($status === Password::RESET_LINK_SENT) {
            // Only count requests that actually dispatched mail, so broker-throttled
            // retries and invalid-email probes don't drain the shared-IP budget for
            // legitimate users behind the same NAT. This keeps the cap tracking
            // "emails this IP caused", matching its mail-bomb / quota-drain intent.
            IpRateLimiter::hit($ipKey, self::DECAY_PER_IP);

            SecurityLog::info('password_reset_requested', 'Password reset link sent', [
                'email' => $request->email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password reset link sent! Check your email.',
            ]);
        }

        // Handle the per-email broker throttle (separate from the per-IP cap above)
        if ($status === Password::RESET_THROTTLED) {
            SecurityLog::info('password_reset_broker_throttled', 'Password reset broker-throttled', [
                'email' => $request->email,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Please wait before requesting another reset link.',
            ], 429);
        }

        // Invalid user (no matching account) — logged to surface enumeration sweeps.
        SecurityLog::info('password_reset_invalid_user', 'Password reset requested for unknown email', [
            'email' => $request->email,
        ]);

        throw ValidationException::withMessages([
            'email' => [trans($status)],
        ]);
    }
}
