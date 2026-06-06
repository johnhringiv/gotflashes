<?php

namespace App\Rules;

use Illuminate\Validation\Rules\Password;

class UserProfileRules
{
    /**
     * Get validation rules for user profile fields.
     *
     * @param  string|null  $userId  For email uniqueness check (null for registration)
     * @param  bool  $includePassword  Whether to include password rules
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?string $userId = null, bool $includePassword = false): array
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => [
                'required',
                'date_format:Y-m-d',
                'before:today',
                'after:1900-01-01',
            ],
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
        ];

        // Email rule (different for registration vs update).
        //
        // 'email:strict' uses egulias/email-validator's NoRFCWarningsValidation
        // (the engine Laravel already bundles). Unlike the bare 'email' rule —
        // which maps to the lenient RFCValidation and accepts junk like "a@b" and
        // "test@localhost" — strict rejects those plus RFC-warning addresses
        // (trailing dots, comments, IP-literal hosts). It remains Unicode-aware,
        // so internationalized addresses (accented local parts, IDN domains) still
        // pass — important for the Class's international fleets. We deliberately
        // avoid the 'dns' mode (200-500ms latency, flaky in CI); the verification
        // email is the real liveness check.
        $emailRule = ['required', 'string', 'email:strict', 'max:255'];
        if ($userId) {
            $emailRule[] = 'unique:users,email,'.$userId;
        } else {
            $emailRule[] = 'unique:users';
        }
        $rules['email'] = $emailRule;

        // Password rules (registration only)
        if ($includePassword) {
            $rules['password'] = ['required', 'confirmed', Password::min(8)];
        }

        return $rules;
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'date_of_birth.date_format' => 'Please enter date in YYYY-MM-DD format',
        ];
    }
}
