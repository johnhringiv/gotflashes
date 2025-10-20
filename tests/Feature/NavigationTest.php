<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_navigation_links(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Home');
        $response->assertSee('Leaderboard');
        $response->assertDontSee('Activities');
    }

    public function test_guest_sees_sign_in_and_sign_up_buttons(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Sign In');
        $response->assertSee('Sign Up');
    }

    public function test_authenticated_user_sees_activities_link(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
        $response->assertSee('Home');
        $response->assertSee('Activities');
        $response->assertSee('Leaderboard');
    }

    public function test_authenticated_user_sees_logout_button(): void
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
        $response->assertSee('John Doe');
        $response->assertSee('Logout');
    }

    public function test_authenticated_user_does_not_see_sign_in_or_sign_up_in_navbar(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
        // Check that the navbar doesn't have Sign In/Sign Up links
        $response->assertDontSee('href="/login"', false);
        $response->assertDontSee('href="/register"', false);
    }

    public function test_mobile_menu_contains_all_navigation_links_for_guest(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        // Mobile menu should have Home and Leaderboard
        $response->assertSee('Home');
        $response->assertSee('Leaderboard');
        // Mobile menu should have Sign In and Sign Up
        $response->assertSee('Sign In');
        $response->assertSee('Sign Up');
    }

    public function test_mobile_menu_contains_all_navigation_links_for_authenticated_user(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
        // Mobile menu should have all nav links
        $response->assertSee('Home');
        $response->assertSee('Activities');
        $response->assertSee('Leaderboard');
        // Mobile menu should have user name and logout
        $response->assertSee('Jane Smith');
        $response->assertSee('Logout');
        $response->assertSee('Account');
    }

    public function test_mobile_hamburger_menu_button_exists(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        // Check for hamburger menu button (aria-label)
        $response->assertSee('Menu', false);
        // Check for the SVG hamburger icon (three lines path)
        $response->assertSee('M4 6h16M4 12h16M4 18h16', false);
    }

    public function test_logout_form_exists_in_mobile_menu_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
        // Check that logout form exists with correct action
        $response->assertSee('action="/logout"', false);
        $response->assertSee('method="POST"', false);
    }

    public function test_navigation_highlights_current_page_home(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        // Home link should have active styling
        $response->assertSee('!text-white !font-bold underline', false);
    }

    public function test_navigation_highlights_current_page_leaderboard(): void
    {
        $response = $this->get('/leaderboard');

        $response->assertStatus(200);
        // Leaderboard link should have active class
        $response->assertSee('active');
    }

    public function test_navigation_highlights_current_page_activities(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/flashes');

        $response->assertStatus(200);
        // Activities link should have active styling
        $response->assertSee('!text-white !font-bold underline', false);
    }

    public function test_desktop_auth_buttons_hidden_on_mobile(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        // Desktop auth section should have hidden lg:flex classes
        $response->assertSee('navbar-end gap-2 hidden lg:flex', false);
    }

    public function test_desktop_menu_hidden_on_mobile(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        // Desktop centered menu should be hidden on mobile
        $response->assertSee('navbar-center hidden lg:flex', false);
    }

    public function test_mobile_menu_hidden_on_desktop(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        // Mobile dropdown should be hidden on desktop (lg screens)
        $response->assertSee('dropdown lg:hidden', false);
    }

    public function test_logo_is_visible_and_links_to_home(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('/images/got_flashes.png', false);
        $response->assertSee('GOT-FLASHES Challenge Tracker');
        $response->assertSee('href="/"', false);
    }

    public function test_mobile_menu_dropdown_has_proper_styling(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        // Check for proper dropdown styling classes
        $response->assertSee('menu dropdown-content mt-3 z-50 p-3 shadow-lg bg-base-100 rounded-box w-56', false);
    }

    public function test_mobile_menu_items_have_readable_text_size(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        // Check that menu items have text-base class for readability
        $response->assertSee('text-base py-3', false);
    }
}
