<?php

namespace App\Livewire;

use App\Services\EmailVerificationService;
use Livewire\Component;

class EmailVerificationBanner extends Component
{
    public function resendVerification()
    {
        $user = auth()->user();

        if (! $user || $user->email_verified_at) {
            return;
        }

        // Check rate limits
        $rateLimitCheck = EmailVerificationService::checkRateLimit($user);

        if (! $rateLimitCheck['allowed']) {
            $this->dispatch('toast', [
                'type' => $rateLimitCheck['type'],
                'message' => $rateLimitCheck['message'],
            ]);

            return;
        }

        // Use service to generate token and send verification email.
        // isNewUser must reflect whether a pending email change is in flight: when
        // pending_email is set, this is a change-verification and must route to (and
        // embed the link for) the NEW address — passing true would misroute it to the
        // current address and invalidate the correctly-routed token. Mirrors ProfileForm.
        EmailVerificationService::requestVerification($user, ! $user->pending_email);

        // Record rate limit attempt
        EmailVerificationService::recordRateLimitAttempt($user);

        // Get the new cooldown time to pass to Alpine
        $cooldownSeconds = EmailVerificationService::getCooldownSeconds($user);

        $this->dispatch('toast', [
            'type' => 'success',
            'message' => 'Verification email sent! Please check your inbox.',
        ]);

        // Dispatch event for Alpine to restart the countdown
        $this->dispatch('verification-sent', cooldown: $cooldownSeconds);
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

        // Get cooldown seconds for countdown display
        $cooldownSeconds = $shouldShow ? EmailVerificationService::getCooldownSeconds($user) : 0;

        return view('livewire.email-verification-banner', [
            'shouldShow' => $shouldShow,
            'cooldownSeconds' => $cooldownSeconds,
        ]);
    }
}
