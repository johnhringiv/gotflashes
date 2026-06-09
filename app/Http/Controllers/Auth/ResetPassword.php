<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class ResetPassword extends Controller
{
    /**
     * Per-IP cap on token submissions — defense-in-depth against token guessing.
     * Low risk already (tokens are long, random, 60-min expiry), but free; 10/hour
     * is far above a legitimate user (who submits once or twice).
     */
    private const MAX_PER_IP = 10;

    private const DECAY_PER_IP = 3600; // seconds (1 hour)

    /**
     * Handle an incoming new password request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email:strict',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Normalize so the lookup matches the lowercased stored email (the reset
        // link carries whatever casing was submitted to the forgot-password form).
        $request->merge(['email' => User::normalizeEmail($request->email)]);

        // Per-IP throttle on token submissions (caps brute-force token guessing).
        $ipKey = 'password-reset:'.$request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, self::MAX_PER_IP)) {
            Log::channel('security')->warning('Password reset submission throttled', [
                'event' => 'password_reset_submit_throttled',
                'email' => $request->email,
                'ip' => $request->ip(),
                'retry_after' => RateLimiter::availableIn($ipKey),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Too many attempts. Please wait a while and try again.',
            ], 429);
        }
        RateLimiter::hit($ipKey, self::DECAY_PER_IP);

        // Attempt to reset the user's password
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                    'email_verified_at' => now(), // Verify email - clicking reset link proves ownership
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // Check if password was reset successfully
        if ($status === Password::PASSWORD_RESET) {
            Log::channel('security')->info('Password reset completed', [
                'event' => 'password_reset_completed',
                'email' => $request->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password reset successful! You can now login.',
                'redirect' => route('login'),
            ]);
        }

        // Failed (bad/expired token or unknown email) — logged for visibility.
        Log::channel('security')->warning('Password reset submission failed', [
            'event' => 'password_reset_failed',
            'email' => $request->email,
            'ip' => $request->ip(),
            'status' => $status,
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);

        // Handle errors
        throw ValidationException::withMessages([
            'email' => [trans($status)],
        ]);
    }
}
