<?php

beforeEach(function () {
    $this->travelTo(frozenJanuary());
});

it('shows empty-state when no users have activity', function () {
    $page = visit('/leaderboard');

    $page->assertSee('No flashes logged yet for '.now()->year);
    $page->assertSee('Be the first');
});
