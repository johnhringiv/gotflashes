<?php

namespace App\Livewire;

use App\Livewire\Concerns\SuggestsEmailCorrections;
use App\Models\District;
use App\Models\Fleet;
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
    use SuggestsEmailCorrections;

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
    // mixed, not ?int: null until touched, then a numeric id (as a string —
    // HTML select values). "Unaffiliated/None" is a real district/fleet row.
    public mixed $district_id = null;

    public mixed $fleet_id = null;

    public string $yacht_club = '';

    public function rules()
    {
        $rules = UserProfileRules::rules(null, true);
        // Override district/fleet to require explicit selection. null/''/0 =
        // untouched (or auto-cleared by the JS when the district changed).
        // These run for both validateOnly() (per-field on blur/change) and
        // validate() (on submit). 'nullable' is intentionally omitted — it
        // short-circuits closures on null values.
        $rules['district_id'] = [
            function ($attr, $value, $fail) {
                if (in_array($value, ['', null, 0, '0'], true)) {
                    $fail('Please select a district or choose Unaffiliated/None.');
                } elseif (! District::where('id', $value)->exists()) {
                    $fail('The selected district is invalid.');
                }
            },
        ];
        $rules['fleet_id'] = [
            function ($attr, $value, $fail) {
                if (in_array($value, ['', null, 0, '0'], true)) {
                    $fail('Please select a fleet or choose None.');
                } elseif ((int) $value !== Fleet::noneId() && ! Fleet::where('id', $value)->where('district_id', $this->district_id)->exists()) {
                    // The None fleet may accompany ANY district; a real fleet
                    // must exist AND belong to the selected district. This
                    // rejects both a cross-district fleet and a fleet with no
                    // district selected (the frontend filters by district and
                    // auto-sets it).
                    $fail('The selected fleet is invalid.');
                }
            },
        ];

        return $rules;
    }

    public function messages()
    {
        return UserProfileRules::messages();
    }

    public function updated($propertyName)
    {
        // Normalize email (lowercase/trim) before live validation so the
        // uniqueness check runs against the same value we'll store. The User
        // model mutator also normalizes on write, but the unique rule queries
        // the raw property, so it must be normalized here too.
        if ($propertyName === 'email') {
            $this->email = User::normalizeEmail($this->email);
            $this->refreshEmailSuggestion();
        }

        // 'confirmed' rule on 'password' already checks password_confirmation,
        // so validate via the 'password' field name for both. Calling
        // validateOnly('password_confirmation') would match no rule, and
        // Livewire's resetErrorBag([]) wipes the entire bag in that case.
        if ($propertyName === 'password' || $propertyName === 'password_confirmation') {
            $this->validateOnly('password');

            return;
        }

        // District/fleet are cleared programmatically (picking a district clears
        // the fleet), so don't flag them while empty — that would error an
        // untouched field. They still validate on submit.
        if (in_array($propertyName, ['district_id', 'fleet_id'], true)
            && in_array($this->{$propertyName}, ['', null, 0, '0'], true)) {
            return;
        }

        $this->validateOnly($propertyName);
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

        // Normalize email before validation so the uniqueness check and the
        // stored value both use the lowercased/trimmed form (covers pasted input
        // that never triggered the updated() hook).
        $this->email = User::normalizeEmail($this->email);

        // Validation uses rules() which includes district/fleet "explicit selection
        // required" closures. Unaffiliated/None is a real district/fleet row, so
        // its ids validate like any other selection.
        $validated = $this->validate();

        // Create user and membership in a transaction
        $user = DB::transaction(function () use ($validated) {
            // Build user data with password and email verification
            $userData = array_merge(
                UserDataService::buildUserData($validated, true),
                UserDataService::generateEmailVerificationData()
            );

            // Create the user
            $user = User::create($userData);

            // Always create membership record for current year (unaffiliated =
            // the None district/fleet)
            Member::create(UserDataService::buildMemberData(
                $user->id,
                (int) $validated['district_id'],
                (int) $validated['fleet_id'],
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

            // Record user-specific rate limit so they can't immediately click "resend"
            EmailVerificationService::recordRateLimitAttempt($user);
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
