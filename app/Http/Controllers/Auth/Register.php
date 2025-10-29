<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class Register extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        // Convert "none" values to null before validation
        $input = $request->all();
        if (isset($input['district_id']) && $input['district_id'] === 'none') {
            $input['district_id'] = null;
        }
        if (isset($input['fleet_id']) && $input['fleet_id'] === 'none') {
            $input['fleet_id'] = null;
        }
        $request->merge($input);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(8)],
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

        // Create user and membership in a transaction to ensure atomicity
        $user = DB::transaction(function () use ($validated) {
            // Create the user
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
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

            // Always create membership record for current year (even if unaffiliated)
            Member::create([
                'user_id' => $user->id,
                'district_id' => $validated['district_id'] ?? null,
                'fleet_id' => $validated['fleet_id'] ?? null,
                'year' => now()->year,
            ]);

            return $user;
        });

        // Log the user in
        Auth::login($user);

        // Redirect to logbook index with success message
        return redirect()->route('logbook.index')->with('success', 'Welcome to G.O.T. Flashes! Your account has been created.');
    }
}
