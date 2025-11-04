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

        // Use service to generate token and send verification email
        EmailVerificationService::requestVerification($user, true);

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
