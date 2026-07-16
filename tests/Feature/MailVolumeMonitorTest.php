<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailVolumeMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_warning_fires_once_when_daily_volume_crosses_threshold(): void
    {
        $warnings = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$warnings) {
            if (($event->context['event'] ?? null) === 'mail_volume_warning') {
                $warnings[] = $event->context;
            }
        });

        // Seed the day's counter to one below the warn threshold (80).
        $key = 'mail-sent-count:'.now()->toDateString();
        Cache::put($key, 79, now()->endOfDay());

        // Two real sends (allowlisted domain, so MessageSent actually fires): the first
        // crosses 80 and warns; the second (81) must NOT warn again the same day.
        for ($i = 0; $i < 2; $i++) {
            Mail::raw('ping', fn ($message) => $message->to('monitor@resend.dev')->subject('volume test'));
        }

        $this->assertCount(1, $warnings);
        $this->assertSame(80, $warnings[0]['sent_today']);
    }

    public function test_no_warning_below_threshold(): void
    {
        $warnings = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$warnings) {
            if (($event->context['event'] ?? null) === 'mail_volume_warning') {
                $warnings[] = $event->context;
            }
        });

        Mail::raw('ping', fn ($message) => $message->to('monitor@resend.dev')->subject('volume test'));

        $this->assertCount(0, $warnings);
    }
}
