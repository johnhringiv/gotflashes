<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Login extends Controller
{
    public function __invoke(Request $request)
    {
        // Validate the input
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Normalize email so a mismatch in casing/whitespace doesn't cause a
        // silent login failure against the lowercased stored value. Both the
        // lookup and Auth::attempt() use the normalized credentials.
        $credentials['email'] = User::normalizeEmail($credentials['email']);

        // Check if user exists
        $user = User::where('email', $credentials['email'])->first();

        if (! $user) {
            // Email doesn't exist in database
            return back()
                ->withErrors(['email' => 'No account found with this email address.'])
                ->onlyInput('email');
        }

        // Attempt to log in
        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            // Regenerate session for security
            $request->session()->regenerate();

            // Redirect to intended page or home
            return redirect()->intended(route('logbook.index'))->with('success', 'Welcome back!');
        }

        // Email exists but password is wrong
        return back()
            ->withErrors(['password' => 'Incorrect password.'])
            ->onlyInput('email');
    }
}
