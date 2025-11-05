<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\VerifyEmailChange;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class EmailVerificationService
{
    /**
     * Rate limit configuration.
     */
    private const RATE_LIMIT_PER_HOUR = 5; // Max 5 emails per hour

    private const RATE_LIMIT_SECONDS = 180; // 3 minutes in seconds

    private const HOURLY_LIMIT_SECONDS = 3600; // 1 hour in seconds

    /**
     * Generate and assign verification token to user.
     */
    public static function generateToken(User $user): string
    {
        $token = Str::random(64);

        $user->update([
            'email_verification_token' => $token,
            'email_verification_expires_at' => now()->addHours(24),
        ]);

        return $token;
    }

    /**
     * Send verification email.
     */
    public static function sendVerification(User $user, bool $isNewUser = true): void
    {
        $user->notify(new VerifyEmailChange($user->email_verification_token, $isNewUser));
    }

    /**
     * Generate token and send verification (convenience method).
     */
    public static function requestVerification(User $user, bool $isNewUser = true): void
    {
        self::generateToken($user);
        self::sendVerification($user, $isNewUser);
    }

    /**
     * Check if user is rate limited and return appropriate response.
     *
     * @return array{allowed: bool, type: string|null, message: string|null}
     */
    public static function checkRateLimit(User $user): array
    {
        $rateLimitKey = 'resend-verification:'.$user->id;

        // Check 3-minute limit
        if (RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $minutes = ceil($seconds / 60);

            return [
                'allowed' => false,
                'type' => 'warning',
                'message' => "Please wait {$minutes} minute(s) before requesting another verification email.",
            ];
        }

        // Check hourly limit
        $hourlyLimitKey = 'resend-verification-hourly:'.$user->id;
        if (RateLimiter::tooManyAttempts($hourlyLimitKey, self::RATE_LIMIT_PER_HOUR)) {
            $minutes = ceil(RateLimiter::availableIn($hourlyLimitKey) / 60);

            return [
                'allowed' => false,
                'type' => 'error',
                'message' => "You've reached the maximum verification emails (".self::RATE_LIMIT_PER_HOUR.' per hour). Please try again in '.$minutes.' minutes.',
            ];
        }

        return [
            'allowed' => true,
            'type' => null,
            'message' => null,
        ];
    }

    /**
     * Record a rate limit attempt.
     */
    public static function recordRateLimitAttempt(User $user): void
    {
        $rateLimitKey = 'resend-verification:'.$user->id;
        $hourlyLimitKey = 'resend-verification-hourly:'.$user->id;

        RateLimiter::hit($rateLimitKey, self::RATE_LIMIT_SECONDS);
        RateLimiter::hit($hourlyLimitKey, self::HOURLY_LIMIT_SECONDS);
    }
}
