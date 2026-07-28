<?php

use App\Models\Flash;
use App\Models\User;

beforeEach(function () {
    $this->travelTo(frozenJanuary());
});

// Guarded against redeclaration: Pest loads test files into a shared scope, so
// a same-named helper in another file would otherwise fatal.
if (! function_exists('e2eSubmitNonSailingFlash')) {
    function e2eSubmitNonSailingFlash($page, string $activityType, string $date): void
    {
        // Use a direct Livewire call — these tests exercise the non-sailing
        // cap, not the date picker UI (which has its own coverage).
        $page->script("
            const formEl = document.querySelector('#activity_type').closest('[wire\\\\:id]');
            const wireId = formEl.getAttribute('wire:id');
            const comp = Livewire.find(wireId);
            comp.set('dates', ['{$date}']);
            comp.set('activity_type', '{$activityType}');
            comp.call('save');
        ");
    }
}

it('shows warning toast when logging 6th non-sailing day', function () {
    $user = User::factory()->create();

    Flash::factory()->forUser($user)->maintenance()->onDate(testDate(2))->create();
    Flash::factory()->forUser($user)->maintenance()->onDate(testDate(3))->create();
    Flash::factory()->forUser($user)->raceCommittee()->onDate(testDate(4))->create();
    Flash::factory()->forUser($user)->raceCommittee()->onDate(testDate(5))->create();
    Flash::factory()->forUser($user)->maintenance()->onDate(testDate(6))->create();

    $this->actingAs($user);
    $page = visit('/logbook');

    e2eSubmitNonSailingFlash($page, 'maintenance', testDate(7));

    waitForToast($page, 'warning');
    $page->assertVisible('#toast-container .alert-warning');
    $page->assertSeeIn('#toast-container', 'non-sailing');
});

it('still saves the 6th non-sailing day despite the warning', function () {
    $user = User::factory()->create();

    for ($i = 2; $i <= 6; $i++) {
        Flash::factory()->forUser($user)->maintenance()->onDate(testDate($i))->create();
    }

    $this->actingAs($user);
    $page = visit('/logbook');

    e2eSubmitNonSailingFlash($page, 'race_committee', testDate(7));

    waitForToast($page, 'warning');
    $page->assertVisible('#toast-container .alert-warning');
    expect($user->flashes()->count())->toBe(6);
});

it('counts both maintenance and race_committee toward the same 5-day cap', function () {
    $user = User::factory()->create();

    Flash::factory()->forUser($user)->maintenance()->onDate(testDate(2))->create();
    Flash::factory()->forUser($user)->maintenance()->onDate(testDate(3))->create();
    Flash::factory()->forUser($user)->maintenance()->onDate(testDate(4))->create();
    Flash::factory()->forUser($user)->raceCommittee()->onDate(testDate(5))->create();
    Flash::factory()->forUser($user)->raceCommittee()->onDate(testDate(6))->create();

    $this->actingAs($user);
    $page = visit('/logbook');

    e2eSubmitNonSailingFlash($page, 'race_committee', testDate(7));

    waitForToast($page, 'warning');
    $page->assertVisible('#toast-container .alert-warning');
    $page->assertSeeIn('#toast-container', 'non-sailing');
});

it('counts the first 5 non-sailing days toward awards but not the 6th', function () {
    $user = User::factory()->create();

    Flash::factory()->forUser($user)->sailing()->onDate(testDate(1))->create(['event_type' => 'regatta']);
    Flash::factory()->forUser($user)->sailing()->onDate(testDate(8))->create(['event_type' => 'practice']);

    for ($i = 2; $i <= 7; $i++) {
        Flash::factory()->forUser($user)->maintenance()->onDate(testDate($i))->create();
    }

    $this->actingAs($user);
    $page = visit('/logbook');

    // The qualifying-day breakdown is the specific assertion; a bare '7' would
    // match dates/ids elsewhere on the page.
    $page->assertSee('2 sailing + 5 non-sailing');
});
