<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class ForgotPassword extends Controller
{
    /**
     * Handle an incoming password reset link request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Send the password reset link
        $status = Password::sendResetLink(
            $request->only('email')
        );

        // Check if the password reset link was sent successfully
        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'success' => true,
                'message' => 'Password reset link sent! Check your email.',
            ]);
        }

        // Handle throttling
        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'success' => false,
                'message' => 'Please wait before requesting another reset link.',
            ], 429);
        }

        // Handle invalid user
        throw ValidationException::withMessages([
            'email' => [trans($status)],
        ]);
    }
}
