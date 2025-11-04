<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\User;
use App\Rules\UserProfileRules;
use App\Services\EmailVerificationService;
use App\Services\UserDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class Register extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        // Normalize district/fleet IDs (convert "none" to null)
        $input = UserDataService::normalizeAffiliationIds($request->all());
        $request->merge($input);

        // Validate using shared rules
        $validated = $request->validate(UserProfileRules::rules(null, true));

        // Create user and membership in a transaction to ensure atomicity
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

        // Send verification email (non-blocking - user can still use the app)
        // Rate limit: max 3 registration emails per IP per hour (prevents abuse)
        $rateLimitKey = 'registration-email:'.$request->ip();

        if (! RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            EmailVerificationService::sendVerification($user, true);
            RateLimiter::hit($rateLimitKey, 3600); // 1 hour decay
        }
        // Note: If rate limited, user still gets registered and logged in,
        // they just won't receive verification email. They can resend from profile.

        // Log the user in
        Auth::login($user);

        // Redirect to logbook index with success message
        return redirect()->route('logbook.index')->with('success', 'Welcome to G.O.T. Flashes! Your account has been created. Please check your email to verify your address.');
    }
}
