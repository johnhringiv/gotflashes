<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class Login extends Controller
{
    /**
     * Per-account brute-force limit: failures for one email from one IP.
     * Keyed on email+IP (not IP alone) so one member's mistyped password can't
     * lock out others on a shared network (family / yacht-club WiFi).
     */
    private const MAX_PER_EMAIL_IP = 5;

    private const DECAY_PER_EMAIL_IP = 60; // seconds (1 minute)

    /**
     * Coarse per-IP backstop against spraying many accounts from one source.
     * Set well above any legitimate shared-IP behaviour (observed busiest shared
     * IP: ~10 emails / 30 days, ~4 failures), so a club night never trips it.
     */
    private const MAX_PER_IP = 25;

    private const DECAY_PER_IP = 900; // seconds (15 minutes)

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

        $emailIpKey = $this->emailIpKey($credentials['email'], $request->ip());
        $ipKey = $this->ipKey($request->ip());

        // Throttle BEFORE touching the database, so a locked-out source can't even
        // probe for account existence. Counts failures only (a success clears them).
        if (RateLimiter::tooManyAttempts($emailIpKey, self::MAX_PER_EMAIL_IP)
            || RateLimiter::tooManyAttempts($ipKey, self::MAX_PER_IP)) {
            return $this->lockedOut($request, $credentials['email'], $emailIpKey, $ipKey);
        }

        // Check if user exists
        $user = User::where('email', $credentials['email'])->first();

        if (! $user) {
            // Email doesn't exist in database
            $this->recordFailure($emailIpKey, $ipKey);

            return back()
                ->withErrors(['email' => 'No account found with this email address.'])
                ->onlyInput('email');
        }

        // Attempt to log in
        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            // Successful login clears this account's per-email+IP counter. The coarse
            // per-IP backstop is left to decay naturally so a single lucky guess on a
            // shared IP doesn't reset spraying protection.
            RateLimiter::clear($emailIpKey);

            // Regenerate session for security
            $request->session()->regenerate();

            // Redirect to intended page or home
            return redirect()->intended(route('logbook.index'))->with('success', 'Welcome back!');
        }

        // Email exists but password is wrong
        $this->recordFailure($emailIpKey, $ipKey);

        return back()
            ->withErrors(['password' => 'Incorrect password.'])
            ->onlyInput('email');
    }

    /**
     * Record a failed attempt against both limiters.
     */
    private function recordFailure(string $emailIpKey, string $ipKey): void
    {
        RateLimiter::hit($emailIpKey, self::DECAY_PER_EMAIL_IP);
        RateLimiter::hit($ipKey, self::DECAY_PER_IP);
    }

    /**
     * Build the locked-out response and log it to the security channel.
     */
    private function lockedOut(Request $request, string $email, string $emailIpKey, string $ipKey)
    {
        $seconds = max(
            RateLimiter::availableIn($emailIpKey),
            RateLimiter::availableIn($ipKey)
        );

        Log::channel('security')->warning('Login throttled', [
            'event' => 'login_throttled',
            'email' => $email,
            'ip' => $request->ip(),
            'retry_after' => $seconds,
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);

        $minutes = (int) ceil($seconds / 60);

        return back()
            ->withErrors(['email' => "Too many login attempts. Please try again in {$minutes} minute(s)."])
            ->onlyInput('email');
    }

    private function emailIpKey(string $email, ?string $ip): string
    {
        return 'login:'.$email.'|'.$ip;
    }

    private function ipKey(?string $ip): string
    {
        return 'login-ip:'.$ip;
    }
}
