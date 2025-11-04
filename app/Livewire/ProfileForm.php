<?php

namespace App\Livewire;

use App\Models\Member;
use App\Notifications\VerifyEmailChange;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

class ProfileForm extends Component
{
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
    public ?int $district_id = null;

    public ?int $fleet_id = null;

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

    public function save()
    {
        $user = auth()->user();

        // Convert "none" values to null before validation
        if ($this->district_id === null || $this->district_id === 0) {
            $this->district_id = null;
        }
        if ($this->fleet_id === null || $this->fleet_id === 0) {
            $this->fleet_id = null;
        }

        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'gender' => ['required', 'in:male,female,non_binary,prefer_not_to_say'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:255'],
            'zip_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:255'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'fleet_id' => ['nullable', 'exists:fleets,id'],
            'yacht_club' => ['nullable', 'string', 'max:255'],
        ]);

        // Check if email has changed
        $emailChanged = $validated['email'] !== $user->email;

        // Update user and membership in a transaction
        DB::transaction(function () use ($user, $validated, $emailChanged) {
            $updateData = [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'date_of_birth' => $validated['date_of_birth'],
                'gender' => $validated['gender'],
                'address_line1' => $validated['address_line1'],
                'address_line2' => $validated['address_line2'] ?? null,
                'city' => $validated['city'],
                'state' => $validated['state'],
                'zip_code' => $validated['zip_code'],
                'country' => $validated['country'],
                'yacht_club' => $validated['yacht_club'] ?? null,
            ];

            // Handle email change with verification
            if ($emailChanged) {
                $token = Str::random(64);
                $updateData['pending_email'] = $validated['email'];
                $updateData['email_verification_token'] = $token;
                $updateData['email_verification_expires_at'] = now()->addHours(24);
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
                    'district_id' => $validated['district_id'] ?? null,
                    'fleet_id' => $validated['fleet_id'] ?? null,
                ]
            );
        });

        // Send verification email if email changed
        if ($emailChanged) {
            // Send verification to new email
            $user->notify(new VerifyEmailChange($user->email_verification_token, false));

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

        // Generate new token and expiration
        $token = Str::random(64);
        $user->update([
            'email_verification_token' => $token,
            'email_verification_expires_at' => now()->addHours(24),
        ]);

        // Send verification email to pending email if exists, otherwise to current email
        $isNewUser = ! $user->pending_email;
        $user->notify(new VerifyEmailChange($token, $isNewUser));

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
        ]);
    }
}
