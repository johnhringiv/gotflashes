<?php

use App\Models\User;

// blade-formatter may wrap multi-word labels ("Sign In") across source lines,
// so collapse whitespace before matching — that's also what the browser renders.
const NAV_TEXT = "document.querySelector('nav').textContent.replace(/\\s+/g, ' ')";

it('shows public links only when anonymous', function () {
    $page = visit('/');
    $page->assertScript(NAV_TEXT.".includes('Leaderboard')", true);
    $page->assertScript(NAV_TEXT.".includes('Sign In')", true);
    $page->assertScript(NAV_TEXT.".includes('Logbook')", false);
    $page->assertScript(NAV_TEXT.".includes('Logout')", false);
});

it('shows authenticated links when logged in', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/');
    $page->assertScript(NAV_TEXT.".includes('Logbook')", true);
    $page->assertScript(NAV_TEXT.".includes('Logout')", true);
    $page->assertScript(NAV_TEXT.".includes('Sign In')", false);
});

it('shows Admin link only for admin users', function () {
    $regular = User::factory()->create();
    $this->actingAs($regular);

    $page = visit('/');
    $page->assertScript(NAV_TEXT.".includes('Award Fulfillment')", false);

    $admin = User::factory()->create();
    $admin->is_admin = true;
    $admin->save();
    $this->actingAs($admin);

    $page2 = visit('/');
    $page2->assertScript(NAV_TEXT.".includes('Award Fulfillment')", true);
});
