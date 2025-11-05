<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserDataService
{
    /**
     * Normalize "none" values to null for district/fleet IDs.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeAffiliationIds(array $data): array
    {
        if (isset($data['district_id']) && in_array($data['district_id'], ['none', '', null, 0], true)) {
            $data['district_id'] = null;
        }
        if (isset($data['fleet_id']) && in_array($data['fleet_id'], ['none', '', null, 0], true)) {
            $data['fleet_id'] = null;
        }

        return $data;
    }

    /**
     * Build user data array from validated input (for create/update).
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function buildUserData(array $validated, bool $includePassword = false): array
    {
        $userData = [
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

        // Include email if in validated data (for registration)
        if (isset($validated['email'])) {
            $userData['email'] = $validated['email'];
        }

        // Include password if specified (registration only)
        if ($includePassword && isset($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        return $userData;
    }

    /**
     * Generate email verification token data.
     *
     * @return array<string, mixed>
     */
    public static function generateEmailVerificationData(): array
    {
        return [
            'email_verification_token' => Str::random(64),
            'email_verification_expires_at' => now()->addHours(24),
        ];
    }

    /**
     * Build member data array.
     *
     * @return array<string, mixed>
     */
    public static function buildMemberData(int $userId, ?int $districtId, ?int $fleetId, int $year): array
    {
        return [
            'user_id' => $userId,
            'district_id' => $districtId,
            'fleet_id' => $fleetId,
            'year' => $year,
        ];
    }
}
