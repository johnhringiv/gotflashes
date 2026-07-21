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
use App\Support\SecurityLog;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ProfileForm extends Component
{
    use SuggestsEmailCorrections;

    // Personal Information
    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $date_of_birth = '';

    public string $gender = '';

    // Address
    public string $address_line1 = '';

    public string $address_line2 = '';

    public string $city = '';

    public string $state = '';

    public string $zip_code = '';

    public string $country = '';

    // Lightning Class Info (from current membership)
    // mixed, not ?int: pre-filled as ints from the membership row, then
    // numeric strings once the JS re-syncs them (HTML select values).
    // fleet_id alone can also be null: the JS auto-clears it when the
    // district changes. "Unaffiliated/None" is a real district/fleet row.
    public mixed $district_id = null;

    public mixed $fleet_id = null;

    public string $yacht_club = '';

    public function mount()
    {
        $user = auth()->user();
        $currentMember = $user->currentMembership();

        // Pre-fill personal information
        $this->first_name = $user->first_name;
        $this->last_name = $user->last_name;
        $this->email = $user->email;
        // @phpstan-ignore method.nonObject, nullsafe.neverNull (date_of_birth is cast to Carbon\Carbon)
        $this->date_of_birth = $user->date_of_birth?->format('Y-m-d') ?? '';
        $this->gender = $user->gender ?? '';

        // Pre-fill address
        $this->address_line1 = $user->address_line1 ?? '';
        $this->address_line2 = $user->address_line2 ?? '';
        $this->city = $user->city ?? '';
        $this->state = $user->state ?? '';
        $this->zip_code = $user->zip_code ?? '';
        $this->country = $user->country ?? '';

        // Pre-fill Lightning Class info from current membership
        if ($currentMember) {
            $this->district_id = $currentMember->district_id;
            $this->fleet_id = $currentMember->fleet_id;
        }

        $this->yacht_club = $user->yacht_club ?? '';
    }

    public function rules()
    {
        $user = auth()->user();
        $rules = UserProfileRules::rules((string) $user->id, false);

        // Same explicit-selection closures as RegistrationForm: null/''/0 =
        // untouched or auto-cleared by the JS. Run for both validateOnly()
        // and validate(); 'nullable' is intentionally omitted — it
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
                    // Reached on save when the JS auto-cleared the fleet after
                    // a district change and the user never re-picked.
                    $fail('Please select a fleet or choose None.');
                } elseif ((int) $value !== Fleet::noneId() && ! Fleet::where('id', $value)->where('district_id', $this->district_id)->exists()) {
                    // The None fleet may accompany ANY district; a real fleet
                    // must exist AND belong to the selected district.
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
        // uniqueness check runs against the same value we'll store.
        if ($propertyName === 'email') {
            $this->email = User::normalizeEmail($this->email);
            $this->refreshEmailSuggestion();
        }

        // District/fleet are cleared programmatically (picking a district clears
        // the fleet), so don't flag them while empty — that would error an
        // untouched field. They still validate on save. Any real pick —
        // including Unaffiliated/None, which is a real row — falls through to
        // validateOnly(), which also clears a stale error from a failed save.
        if (in_array($propertyName, ['district_id', 'fleet_id'], true)
            && in_array($this->{$propertyName}, ['', null, 0, '0'], true)) {
            return;
        }

        // Validate the field that was just updated
        $this->validateOnly($propertyName);
    }

    public function save()
    {
        $user = auth()->user();

        // Normalize email before validation/comparison so the uniqueness check,
        // the email-changed comparison, and the stored value all use the
        // lowercased/trimmed form.
        $this->email = User::normalizeEmail($this->email);

        // rules() requires an explicit district and fleet (Unaffiliated/None is
        // a real row); an empty fleet here means the JS auto-cleared it on a
        // district change and the user never re-picked.
        $validated = $this->validate();

        // Check if email has changed
        $emailChanged = $validated['email'] !== $user->email;

        // Update user and membership in a transaction
        DB::transaction(function () use ($user, $validated, $emailChanged) {
            // Build update data (exclude email - we handle it separately)
            $profileData = $validated;
            unset($profileData['email']);
            $updateData = UserDataService::buildUserData($profileData, false);

            // Handle email change with verification
            if ($emailChanged) {
                $updateData = array_merge(
                    $updateData,
                    ['pending_email' => $validated['email']],
                    UserDataService::generateEmailVerificationData()
                );
            }

            // Update user
            $user->update($updateData);

            // Update or create current year membership
            Member::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'year' => now()->year,
                ],
                [
                    'district_id' => (int) $validated['district_id'],
                    'fleet_id' => (int) $validated['fleet_id'],
                ]
            );
        });

        // Send verification email if email changed
        if ($emailChanged) {
            // Account-takeover-relevant transition: a new login email is being requested.
            // ($user->email is still the old address here — only pending_email was updated.)
            SecurityLog::info('email_change_initiated', 'Email change requested', [
                'user_id' => $user->id,
                'old_email' => $user->email,
                'new_email' => $validated['email'],
            ]);

            // Send verification to new email
            EmailVerificationService::sendVerification($user, false);

            $this->dispatch('toast', [
                'type' => 'success',
                'message' => 'Profile updated! Please check your new email to verify the change.',
            ]);

            // Update the component's email to show the current (not pending) email
            $this->email = $user->email;
        } else {
            $this->dispatch('toast', [
                'type' => 'success',
                'message' => 'Profile updated successfully!',
            ]);
        }
    }

    public function resendEmailVerification()
    {
        $user = auth()->user();

        if (! $user->email_verification_token) {
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

        // Generate new token and send verification
        $isNewUser = ! $user->pending_email;
        EmailVerificationService::requestVerification($user, $isNewUser);

        // Record rate limit attempt
        EmailVerificationService::recordRateLimitAttempt($user);

        $this->dispatch('toast', [
            'type' => 'success',
            'message' => 'Verification email sent! Please check your inbox.',
        ]);
    }

    public function cancelEmailChange()
    {
        $user = auth()->user();

        if (! $user->pending_email) {
            return;
        }

        // Clear pending email and verification data
        $user->update([
            'pending_email' => null,
            'email_verification_token' => null,
            'email_verification_expires_at' => null,
        ]);

        // Reset the component's email to current email
        $this->email = $user->email;

        $this->dispatch('toast', [
            'type' => 'success',
            'message' => 'Email change cancelled.',
        ]);
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.profile-form', [
            'hasPendingEmail' => (bool) $user->pending_email,
            'pendingEmail' => $user->pending_email,
            'isEmailVerified' => (bool) $user->email_verified_at,
            'hasFlashes' => $user->flashes()->exists(),
        ]);
    }
}
