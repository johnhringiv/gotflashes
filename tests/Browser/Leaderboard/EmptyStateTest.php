<?php

use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2027-01-15 12:00:00'));
});

it('shows empty-state when no users have activity', function () {
    $page = visit('/leaderboard');

    $page->assertSee('No flashes logged yet for 2027');
    $page->assertSee('Be the first');
});
