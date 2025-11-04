<?php

namespace App\Livewire;

use App\Notifications\VerifyEmailChange;
use Illuminate\Support\Str;
use Livewire\Component;

class EmailVerificationBanner extends Component
{
    public function resendVerification()
    {
        $user = auth()->user();

        if (! $user || $user->email_verified_at) {
            return;
        }

        // Generate new token and expiration
        $token = Str::random(64);
        $user->update([
            'email_verification_token' => $token,
            'email_verification_expires_at' => now()->addHours(24),
        ]);

        // Send verification email
        $user->notify(new VerifyEmailChange($token, true));

        $this->dispatch('toast', [
            'type' => 'success',
            'message' => 'Verification email sent! Please check your inbox.',
        ]);
    }

    public function render()
    {
        $user = auth()->user();

        // Get fresh user data from database to ensure we have latest email_verified_at
        if ($user) {
            $user = $user->fresh();
        }

        // Only show if user is authenticated and email is not verified
        $shouldShow = $user && ! $user->email_verified_at;

        return view('livewire.email-verification-banner', [
            'shouldShow' => $shouldShow,
        ]);
    }
}
