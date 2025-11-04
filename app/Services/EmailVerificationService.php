<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\VerifyEmailChange;
use Illuminate\Support\Str;

class EmailVerificationService
{
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
}
