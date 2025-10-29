<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotFoundPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_page_returns_correct_status_code(): void
    {
        $response = $this->get('/this-route-does-not-exist');

        $response->assertStatus(404);
    }

    public function test_guest_can_view_404_page(): void
    {
        $response = $this->get('/nonexistent-page');

        $response->assertStatus(404);
        $response->assertSee('404');
        $response->assertSee('Lost at Sea?');
    }

    public function test_authenticated_user_can_view_404_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/nonexistent-page');

        $response->assertStatus(404);
        $response->assertSee('404');
        $response->assertSee('Lost at Sea?');
    }

    public function test_404_page_shows_guest_navigation(): void
    {
        $response = $this->get('/nonexistent-page');

        $response->assertStatus(404);
        // Guest should see Sign In and Sign Up links
        $response->assertSee('Sign In');
        $response->assertSee('Sign Up');
        // Guest should NOT see Logbook link
        $response->assertDontSee('Logbook');
    }

    public function test_404_page_shows_authenticated_navigation(): void
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->actingAs($user)->get('/nonexistent-page');

        $response->assertStatus(404);
        // Authenticated user should see Logbook link
        $response->assertSee('Logbook');
        // Authenticated user should see their name
        $response->assertSee('John Doe');
        // Authenticated user should NOT see Sign In/Sign Up
        $response->assertDontSee('href="/login"', false);
        $response->assertDontSee('href="/register"', false);
    }

    public function test_404_page_has_navigation_links_to_home(): void
    {
        $response = $this->get('/nonexistent-page');

        $response->assertStatus(404);
        $response->assertSee('Home');
        $response->assertSee('href="/"', false);
    }

    public function test_404_page_has_navigation_links_to_leaderboard(): void
    {
        $response = $this->get('/nonexistent-page');

        $response->assertStatus(404);
        $response->assertSee('Leaderboard');
        $response->assertSee('href="/leaderboard"', false);
    }

    public function test_404_page_displays_helpful_error_message(): void
    {
        $response = $this->get('/nonexistent-page');

        $response->assertStatus(404);
        $response->assertSee('Lost at Sea?');
        $response->assertSee('drifted off course');
        $response->assertSee('Where would you like to sail?');
    }

    public function test_404_page_shows_lightning_logo(): void
    {
        $response = $this->get('/nonexistent-page');

        $response->assertStatus(404);
        $response->assertSee('/images/lightning_logo.png', false);
        $response->assertSee('Lightning Class');
    }

    public function test_404_page_has_helpful_cta_buttons(): void
    {
        $response = $this->get('/nonexistent-page');

        $response->assertStatus(404);
        // Check for Home and Leaderboard CTAs
        $response->assertSee('Home');
        $response->assertSee('Leaderboard');
        // Check for proper button styling
        $response->assertSee('btn btn-primary', false);
        $response->assertSee('btn btn-accent', false);
    }

    public function test_404_page_uses_layout_component(): void
    {
        $response = $this->get('/nonexistent-page');

        $response->assertStatus(404);
        // Layout component includes header/footer navigation
        $response->assertSee('G.O.T. Flashes Challenge Tracker');
        $response->assertSee('/images/got_flashes.png', false);
    }

    public function test_404_page_includes_helpful_tip_alert(): void
    {
        $response = $this->get('/nonexistent-page');

        $response->assertStatus(404);
        $response->assertSee('Need help?');
        $response->assertSee('home page');
    }

    public function test_session_middleware_works_on_404_page(): void
    {
        // Create a user and add session data
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['test_key' => 'test_value'])
            ->get('/nonexistent-page');

        $response->assertStatus(404);
        // If session middleware is working, authentication state should be preserved
        $this->assertAuthenticated();
    }

    public function test_404_page_does_not_leak_sensitive_information(): void
    {
        $response = $this->get('/nonexistent-page');

        $response->assertStatus(404);
        // Should NOT show stack traces or exception details
        $response->assertDontSee('stack trace');
        $response->assertDontSee('Exception');
        $response->assertDontSee('Whoops');
        $response->assertDontSee('Illuminate\\');
    }

    public function test_404_page_with_deeply_nested_route(): void
    {
        $response = $this->get('/some/deeply/nested/route/that/does/not/exist');

        $response->assertStatus(404);
        $response->assertSee('404');
        $response->assertSee('Lost at Sea?');
    }

    public function test_404_page_with_query_parameters(): void
    {
        $response = $this->get('/nonexistent?foo=bar&baz=qux');

        $response->assertStatus(404);
        $response->assertSee('404');
        $response->assertSee('Lost at Sea?');
    }

    public function test_404_page_respects_meta_tags(): void
    {
        $response = $this->get('/nonexistent-page');

        $response->assertStatus(404);
        // Check for proper meta description (from layout slot)
        $response->assertSee('404 - Page Not Found', false);
        $response->assertSee("The page you're looking for doesn't exist", false);
    }

    public function test_404_page_has_proper_html_structure(): void
    {
        $response = $this->get('/nonexistent-page');

        $response->assertStatus(404);
        // Should have proper card structure
        $response->assertSee('card bg-base-100 shadow-xl', false);
        $response->assertSee('card-body', false);
    }

    public function test_existing_routes_do_not_trigger_404(): void
    {
        // Verify that valid routes still work
        $response = $this->get('/');
        $response->assertStatus(200);

        $response = $this->get('/leaderboard');
        $response->assertStatus(200);

        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_protected_routes_redirect_instead_of_404(): void
    {
        // Authenticated-only routes should redirect to login, not 404
        $response = $this->get('/logbook');

        $response->assertRedirect('/login');
        $response->assertStatus(302); // Not 404
    }
}
