<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

// Mail recipient allowlist — blocks non-allowed domains in non-production.
class MailAllowlistProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Fail-safe default: enforce unless explicitly disabled.
        // Treat unset OR empty string (MAIL_ALLOWLIST_ENABLED= in .env, which CI
        // copies) the same — "enforce in non-production" — so a blank value can't
        // silently disable the guard.
        $explicit = config('mail.allowlist_enabled');
        if ($explicit === null || $explicit === '') {
            $enabled = ! $this->app->environment('production');
        } else {
            $enabled = filter_var($explicit, FILTER_VALIDATE_BOOLEAN);
        }

        if (! $enabled) {
            return;
        }

        $allowed = array_map(
            'trim',
            explode(',', (string) config('mail.allowed_domains', 'resend.dev'))
        );
        $allowed = array_filter(array_map('strtolower', $allowed));

        if ($allowed === []) {
            // Misconfiguration: enabled but no domains. Stay fail-safe — the
            // listener below blocks every recipient when the allowlist is empty —
            // but log loudly so this isn't a silent total block.
            Log::warning('MailAllowlistProvider is enabled but MAIL_ALLOWED_DOMAINS is empty — all outbound mail will be blocked.');
        }

        Event::listen(MessageSending::class, function (MessageSending $event) use ($allowed) {
            $message = $event->message;
            $recipients = array_merge(
                $message->getTo(),
                $message->getCc(),
                $message->getBcc()
            );

            foreach ($recipients as $address) {
                $email = $address->getAddress();
                $atPos = strrpos($email, '@');
                $domain = $atPos === false ? '' : strtolower(substr($email, $atPos + 1));

                if (! in_array($domain, $allowed, true)) {
                    Log::warning('MailAllowlistProvider blocked send', [
                        'recipient' => $email,
                        'domain' => $domain,
                        'allowed' => $allowed,
                        'subject' => $message->getSubject(),
                    ]);

                    // Returning false from a MessageSending listener cancels
                    // the send — the underlying transport never receives the message.
                    return false;
                }
            }

            return null; // explicit no-op; let send proceed
        });
    }
}
