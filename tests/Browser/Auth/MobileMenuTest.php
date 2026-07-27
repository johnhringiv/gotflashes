<?php

// The mobile hamburger menu is pure CSS (no JS): .dropdown-content is hidden
// until the trigger gains focus (.dropdown:focus-within reveals it), and while
// open the trigger gets pointer-events: none so a second tap falls through,
// blurs it, and closes the menu. These tests cover the hidden-by-default state
// (which once regressed to permanently-expanded during the framework removal),
// the open-on-real-click path, and close-on-focus-loss — the mechanism behind
// both outside-tap and re-tap dismissal. The literal re-tap fall-through can't
// be driven here: Playwright's actionability checks refuse to click a
// pointer-events: none element, so that last leg is verified manually with raw
// coordinate clicks (see CLAUDE.md's Livewire/JS integration notes).

const MENU_DISPLAY = "getComputedStyle(document.querySelector('.dropdown-content')).display";

it('starts collapsed on mobile and opens on hamburger tap', function () {
    $page = visit('/')->resize(400, 850);

    $page->assertScript(MENU_DISPLAY, 'none');

    $page->click('button[aria-label="Menu"]');
    $page->assertScript(MENU_DISPLAY, 'flex');
});

it('closes when the trigger loses focus', function () {
    $page = visit('/')->resize(400, 850);

    $page->click('button[aria-label="Menu"]');
    $page->assertScript(MENU_DISPLAY, 'flex');

    $page->script('document.activeElement.blur()');
    $page->assertScript(MENU_DISPLAY, 'none');
});

it('hides the hamburger on desktop viewports', function () {
    $page = visit('/')->resize(1280, 860);

    $page->assertScript(
        "getComputedStyle(document.querySelector('.dropdown.lg\\\\:hidden')).display",
        'none',
    );
});
