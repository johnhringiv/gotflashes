<?php

namespace App\Livewire;

use App\Models\Member;
use Illuminate\Support\Facades\DB;
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

        // Update user and membership in a transaction
        DB::transaction(function () use ($user, $validated) {
            // Update user (email is not editable)
            $user->update([
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
            ]);

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

        $this->dispatch('toast', [
            'type' => 'success',
            'message' => 'Profile updated successfully!',
        ]);
    }

    public function render()
    {
        return view('livewire.profile-form');
    }
}
