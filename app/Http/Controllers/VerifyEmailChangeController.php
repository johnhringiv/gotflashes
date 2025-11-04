<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class VerifyEmailChangeController extends Controller
{
    /**
     * Handle email verification.
     */
    public function __invoke(Request $request, string $token)
    {
        // Find user by token
        $user = User::where('email_verification_token', $token)->first();

        if (! $user) {
            return redirect()->route('logbook.index')
                ->with('error', 'Invalid verification link.');
        }

        // Check if token has expired
        // @phpstan-ignore method.nonObject (email_verification_expires_at is cast to Carbon\Carbon)
        if ($user->email_verification_expires_at && $user->email_verification_expires_at->isPast()) {
            return redirect()->route('profile')
                ->with('error', 'This verification link has expired. Please request a new one from your profile.');
        }

        // Check if this is a new user verification or email change
        if ($user->pending_email) {
            // Email change: Move pending_email to email
            $user->update([
                'email' => $user->pending_email,
                'email_verified_at' => now(),
                'pending_email' => null,
                'email_verification_token' => null,
                'email_verification_expires_at' => null,
            ]);

            // Refresh the authenticated user session if this is the logged-in user
            if (auth()->check() && auth()->id() === $user->id) {
                auth()->setUser($user->fresh());
            }

            return redirect()->route('profile')
                ->with('success', 'Your email has been successfully updated!');
        } else {
            // New user verification
            $user->update([
                'email_verified_at' => now(),
                'email_verification_token' => null,
                'email_verification_expires_at' => null,
            ]);

            // Refresh the authenticated user session if this is the logged-in user
            if (auth()->check() && auth()->id() === $user->id) {
                auth()->setUser($user->fresh());
            }

            return redirect()->route('logbook.index')
                ->with('success', 'Your email has been verified! Thank you.');
        }
    }
}
