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
}
