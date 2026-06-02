<?php

use App\Models\User;

it('shows public links only when anonymous', function () {
    $page = visit('/');
    $page->assertScript("document.querySelector('nav').textContent.includes('Leaderboard')", true);
    $page->assertScript("document.querySelector('nav').textContent.includes('Sign In')", true);
    $page->assertScript("document.querySelector('nav').textContent.includes('Logbook')", false);
    $page->assertScript("document.querySelector('nav').textContent.includes('Logout')", false);
});

it('shows authenticated links when logged in', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/');
    $page->assertScript("document.querySelector('nav').textContent.includes('Logbook')", true);
    $page->assertScript("document.querySelector('nav').textContent.includes('Logout')", true);
    $page->assertScript("document.querySelector('nav').textContent.includes('Sign In')", false);
});

it('shows Admin link only for admin users', function () {
    $regular = User::factory()->create();
    $this->actingAs($regular);

    $page = visit('/');
    $page->assertScript("document.querySelector('nav').textContent.includes('Award Fulfillment')", false);

    $admin = User::factory()->create();
    $admin->is_admin = true;
    $admin->save();
    $this->actingAs($admin);

    $page2 = visit('/');
    $page2->assertScript("document.querySelector('nav').textContent.includes('Award Fulfillment')", true);
});
