<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\ThrottlesByIp;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\IpRateLimiter;
use App\Support\SecurityLog;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class ResetPassword extends Controller
{
    use ThrottlesByIp;

    /**
     * Per-IP cap on token submissions — defense-in-depth against token guessing.
     * Low risk already (tokens are long, random, 60-min expiry), but free; 10/hour
     * is far above a legitimate user (who submits once or twice). Every submission
     * is a guess attempt, so all of them count (unlike the send-only cap on the
     * forgot-password flow).
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
        $ipKey = IpRateLimiter::ipKey('password-reset', $request->ip());
        if ($throttled = $this->throttledJsonResponse(
            $ipKey,
            self::MAX_PER_IP,
            'password_reset_submit_throttled',
            'Password reset submission throttled',
            'Too many attempts. Please wait a while and try again.'
        )) {
            return $throttled;
        }
        IpRateLimiter::hit($ipKey, self::DECAY_PER_IP);

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
            SecurityLog::info('password_reset_completed', 'Password reset completed', [
                'email' => $request->email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password reset successful! You can now login.',
                'redirect' => route('login'),
            ]);
        }

        // Failed (bad/expired token or unknown email) — logged for visibility.
        SecurityLog::warning('password_reset_failed', 'Password reset submission failed', [
            'email' => $request->email,
            'status' => $status,
        ]);

        // Handle errors
        throw ValidationException::withMessages([
            'email' => [trans($status)],
        ]);
    }
}
