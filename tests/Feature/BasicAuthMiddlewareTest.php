<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BasicAuthMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Put the app into a gated (deployed, non-local/testing) environment with basic-auth
     * credentials configured. The suite otherwise runs as `testing`, where the gate is a
     * deliberate no-op, so the 401 path can only be exercised by overriding the env.
     */
    private function gateAsStaging(): void
    {
        $this->app['env'] = 'staging';
        config([
            'auth.basic.username' => 'devuser',
            'auth.basic.password' => 'devpass',
        ]);
    }

    private function authHeader(string $user, string $pass): array
    {
        return ['Authorization' => 'Basic '.base64_encode($user.':'.$pass)];
    }

    public function test_gated_environment_challenges_when_no_credentials_supplied(): void
    {
        $this->gateAsStaging();

        $this->get('/')
            ->assertStatus(401)
            ->assertHeader('WWW-Authenticate', 'Basic realm="G.O.T. Flashes Staging"');
    }

    public function test_the_401_challenge_still_carries_the_noindex_header(): void
    {
        // Pins the middleware ordering: PreventIndexingNonProduction is appended ahead of
        // BasicAuthMiddleware, so even its short-circuit 401 is wrapped and gets X-Robots-Tag.
        $this->gateAsStaging();

        $this->get('/')
            ->assertStatus(401)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_correct_credentials_pass_through(): void
    {
        $this->gateAsStaging();

        $this->withHeaders($this->authHeader('devuser', 'devpass'))
            ->get('/')
            ->assertStatus(200);
    }

    public function test_gate_is_a_noop_in_the_testing_environment(): void
    {
        // Default suite env is 'testing' — the gate must not fire even with creds set,
        // so local/CI request cycles are never challenged.
        config([
            'auth.basic.username' => 'devuser',
            'auth.basic.password' => 'devpass',
        ]);

        $this->get('/')->assertStatus(200);
    }

    public function test_blank_credentials_fall_through_even_in_a_gated_environment(): void
    {
        // With no BASIC_AUTH_* configured the gate falls through (noindex still applies).
        $this->app['env'] = 'staging';
        config(['auth.basic.username' => null, 'auth.basic.password' => null]);

        $this->get('/')->assertStatus(200);
    }

    public function test_failed_attempt_is_logged_without_the_submitted_password(): void
    {
        $entries = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$entries) {
            if (($event->context['event'] ?? null) === 'basic_auth_failed') {
                $entries[] = $event->context;
            }
        });

        $this->gateAsStaging();

        $this->withHeaders($this->authHeader('devuser', 'wrong-password'))
            ->get('/')
            ->assertStatus(401);

        $this->assertCount(1, $entries);
        $this->assertSame('devuser', $entries[0]['attempted_username']);
        // The submitted password must never reach the logs.
        $this->assertStringNotContainsString('wrong-password', json_encode($entries[0]));
    }
}
