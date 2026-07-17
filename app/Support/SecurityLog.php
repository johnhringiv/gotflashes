<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Thin helper for the `security` log channel.
 *
 * Injects request context (ip / user_agent / request_id) once so call sites can
 * pass just the event slug, a human message, and event-specific fields. There is
 * deliberately no `timestamp` field: the channel's Monolog JsonFormatter already
 * emits `datetime`, so a hand-added timestamp only duplicates it.
 *
 * The request_id ties a security event back to the matching RequestLoggingMiddleware
 * entry on the `structured` channel (it sets the X-Request-ID header per request).
 */
class SecurityLog
{
    public static function info(string $event, string $message, array $context = []): void
    {
        self::write('info', $event, $message, $context);
    }

    public static function warning(string $event, string $message, array $context = []): void
    {
        self::write('warning', $event, $message, $context);
    }

    private static function write(string $level, string $event, string $message, array $context): void
    {
        $request = request();

        // Filter the auto-injected fields (null in console/queue contexts); leave the
        // caller's context untouched so intentional nulls (e.g. user_id) are preserved.
        $base = array_filter([
            'event' => $event,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->headers->get('X-Request-ID'),
        ], static fn ($value) => $value !== null);

        $payload = array_merge($base, $context);

        $logger = Log::channel('security');

        match ($level) {
            'warning' => $logger->warning($message, $payload),
            default => $logger->info($message, $payload),
        };
    }
}
