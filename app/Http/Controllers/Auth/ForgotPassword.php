<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ForgotPassword extends Controller
{
    /**
     * Per-IP cap on reset-link requests, on top of the per-email broker throttle
     * (config/auth.php 'throttle' => 60). The broker throttle is per-email, so it
     * does nothing against one source requesting resets across many accounts —
     * mail-bombing / enumeration / single-IP quota drain. This closes that gap.
     * Resets are rare for real users, so 5/hour is generous even on a shared
     * club/family IP. Distributed drains are surfaced by the warn-only mail monitor.
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

        // Per-IP throttle — counts every request (valid or not) so enumeration sweeps
        // and mail-bombing from one source are capped, each of which would send an email.
        $ipKey = 'password-email:'.$request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, self::MAX_PER_IP)) {
            Log::channel('security')->warning('Password reset request throttled', [
                'event' => 'password_reset_throttled',
                'email' => $request->email,
                'ip' => $request->ip(),
                'retry_after' => RateLimiter::availableIn($ipKey),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Too many reset requests. Please wait a while and try again.',
            ], 429);
        }
        RateLimiter::hit($ipKey, self::DECAY_PER_IP);

        // Send the password reset link
        $status = Password::sendResetLink(
            $request->only('email')
        );

        // Check if the password reset link was sent successfully
        if ($status === Password::RESET_LINK_SENT) {
            Log::channel('security')->info('Password reset link sent', [
                'event' => 'password_reset_requested',
                'email' => $request->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password reset link sent! Check your email.',
            ]);
        }

        // Handle the per-email broker throttle (separate from the per-IP cap above)
        if ($status === Password::RESET_THROTTLED) {
            Log::channel('security')->info('Password reset broker-throttled', [
                'event' => 'password_reset_broker_throttled',
                'email' => $request->email,
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Please wait before requesting another reset link.',
            ], 429);
        }

        // Invalid user (no matching account) — logged to surface enumeration sweeps.
        Log::channel('security')->info('Password reset requested for unknown email', [
            'event' => 'password_reset_invalid_user',
            'email' => $request->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);

        throw ValidationException::withMessages([
            'email' => [trans($status)],
        ]);
    }
}
