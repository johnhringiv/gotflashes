<?php

namespace App\Livewire;

use App\Models\Member;
use App\Models\User;
use App\Rules\UserProfileRules;
use App\Services\EmailVerificationService;
use App\Services\UserDataService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class RegistrationForm extends Component
{
    // Personal Information
    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $date_of_birth = '';

    public string $gender = '';

    // Address
    public string $address_line1 = '';

    public string $address_line2 = '';

    public string $city = '';

    public string $state = '';

    public string $zip_code = '';

    public string $country = 'United States';

    // Lightning Class Info
    public ?int $district_id = null;

    public ?int $fleet_id = null;

    public string $yacht_club = '';

    public function rules()
    {
        return UserProfileRules::rules(null, true);
    }

    public function messages()
    {
        return UserProfileRules::messages();
    }

    public function updated($propertyName)
    {
        // For password fields, validate both together
        if ($propertyName === 'password' || $propertyName === 'password_confirmation') {
            $this->validateOnly('password');
            $this->validateOnly('password_confirmation');
        } else {
            // Validate the field that was just updated
            $this->validateOnly($propertyName);
        }
    }

    public function register()
    {
        // Rate limit registrations per IP: max 5 per hour
        $ipAddress = request()->ip();
        $rateLimitKey = 'registration:'.$ipAddress;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $minutes = ceil(RateLimiter::availableIn($rateLimitKey) / 60);

            $this->dispatch('toast', [
                'type' => 'error',
                'message' => "Too many registration attempts. Please try again in {$minutes} minutes.",
            ]);

            return;
        }

        // Convert "none" values to null before validation
        if ($this->district_id === 0) {
            $this->district_id = null;
        }
        if ($this->fleet_id === 0) {
            $this->fleet_id = null;
        }

        // Validate using shared rules (including password for registration)
        $validated = $this->validate(UserProfileRules::rules(null, true));

        // Create user and membership in a transaction
        $user = DB::transaction(function () use ($validated) {
            // Build user data with password and email verification
            $userData = array_merge(
                UserDataService::buildUserData($validated, true),
                UserDataService::generateEmailVerificationData()
            );

            // Create the user
            $user = User::create($userData);

            // Always create membership record for current year (even if unaffiliated)
            Member::create(UserDataService::buildMemberData(
                $user->id,
                $validated['district_id'] ?? null,
                $validated['fleet_id'] ?? null,
                now()->year
            ));

            return $user;
        });

        // Record registration attempt for rate limiting (1 hour = 3600 seconds)
        RateLimiter::hit($rateLimitKey, 3600);

        // Send verification email (non-blocking - user can still use the app)
        // Rate limit verification emails per IP: max 3 per hour
        $emailRateLimitKey = 'registration-email:'.$ipAddress;

        if (! RateLimiter::tooManyAttempts($emailRateLimitKey, 3)) {
            EmailVerificationService::sendVerification($user, true);
            RateLimiter::hit($emailRateLimitKey, 3600);
        }
        // Note: If email rate limited, user still gets registered and logged in,
        // they just won't receive verification email. They can resend from profile.

        // Log the user in
        Auth::login($user);

        // Redirect to logbook with success message
        return redirect()->route('logbook.index')->with('success', 'Welcome to G.O.T. Flashes! Your account has been created. Please check your email to verify your address.');
    }

    public function render()
    {
        return view('livewire.registration-form');
    }
}
