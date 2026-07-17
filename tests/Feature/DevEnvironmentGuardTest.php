<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevEnvironmentGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_noindex_header_is_sent_in_non_production(): void
    {
        // The test suite runs as a non-production environment, so the guard applies.
        $this->assertFalse(app()->environment('production'));

        $this->get('/')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_https_is_not_forced_in_local_or_testing(): void
    {
        // AppServiceProvider forces https on every deployed env (! local/testing) so
        // staging URLs/forms aren't emitted as http:// behind upstream TLS. This guards
        // the exclusion: if it regressed to include testing, generated URLs would flip
        // to https and break the local/CI request cycle. (Staging/prod https itself is
        // verified manually — the app boots once as 'testing' in the suite.)
        $this->assertTrue(app()->environment('testing'));
        $this->assertStringStartsWith('http://', route('login'));
    }
}
