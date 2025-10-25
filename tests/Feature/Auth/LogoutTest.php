<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_guests_cannot_logout(): void
    {
        $response = $this->post('/logout');

        $response->assertRedirect('/login');
    }

    public function test_get_logout_redirects_to_home(): void
    {
        // GET request to /logout should redirect to home (no error)
        $response = $this->get('/logout');

        $response->assertRedirect('/');
    }

    public function test_authenticated_user_get_logout_redirects_to_home(): void
    {
        $user = User::factory()->create();

        // Even authenticated users hitting GET /logout should be redirected
        // (they should use POST for actual logout)
        $response = $this->actingAs($user)->get('/logout');

        $response->assertRedirect('/');
        $this->assertAuthenticated(); // Still authenticated (didn't logout)
    }
}
