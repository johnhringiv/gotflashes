<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class ResetPassword extends Controller
{
    /**
     * Handle an incoming new password request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Attempt to reset the user's password
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                    'email_verified_at' => now(), // Verify email - clicking reset link proves ownership
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // Check if password was reset successfully
        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Password reset successful! You can now login.',
                'redirect' => route('login'),
            ]);
        }

        // Handle errors
        throw ValidationException::withMessages([
            'email' => [trans($status)],
        ]);
    }
}
