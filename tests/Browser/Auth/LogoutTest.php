<?php

use App\Models\User;

it('logs out and returns to home page with anonymous nav', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/logbook');
    $page->assertPathIs('/logbook');

    // Click the visible Logout button (desktop nav - btn-error class)
    $page->click('button.btn-error')
        ->assertPathIs('/')
        ->assertSee('Sign In')
        ->assertSee('Sign Up');

    // Verify /logbook redirects to /login when logged out
    $page2 = visit('/logbook');
    $page2->assertPathIs('/login');
});
