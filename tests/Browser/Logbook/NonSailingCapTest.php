<?php

use App\Models\Flash;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2027-01-15 12:00:00'));
});

// Guarded against redeclaration: Pest loads test files into a shared scope, so
// a same-named helper in another file would otherwise fatal.
if (! function_exists('e2eSubmitNonSailingFlash')) {
    function e2eSubmitNonSailingFlash($page, string $activityType, string $date): void
    {
        // Use a direct Livewire call — flatpickr doesn't reliably sync the
        // selected dates to Livewire via UI interaction in tests.
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

    Flash::factory()->forUser($user)->maintenance()->onDate('2027-01-02')->create();
    Flash::factory()->forUser($user)->maintenance()->onDate('2027-01-03')->create();
    Flash::factory()->forUser($user)->raceCommittee()->onDate('2027-01-04')->create();
    Flash::factory()->forUser($user)->raceCommittee()->onDate('2027-01-05')->create();
    Flash::factory()->forUser($user)->maintenance()->onDate('2027-01-06')->create();

    $this->actingAs($user);
    $page = visit('/logbook');

    e2eSubmitNonSailingFlash($page, 'maintenance', '2027-01-07');

    $page->assertVisible('#toast-container .alert-warning');
    $page->assertSeeIn('#toast-container', 'non-sailing');
});

it('still saves the 6th non-sailing day despite the warning', function () {
    $user = User::factory()->create();

    for ($i = 2; $i <= 6; $i++) {
        Flash::factory()->forUser($user)->maintenance()->onDate("2027-01-0{$i}")->create();
    }

    $this->actingAs($user);
    $page = visit('/logbook');

    e2eSubmitNonSailingFlash($page, 'race_committee', '2027-01-07');

    $page->assertVisible('#toast-container .alert-warning');
    expect($user->flashes()->count())->toBe(6);
});

it('counts both maintenance and race_committee toward the same 5-day cap', function () {
    $user = User::factory()->create();

    Flash::factory()->forUser($user)->maintenance()->onDate('2027-01-02')->create();
    Flash::factory()->forUser($user)->maintenance()->onDate('2027-01-03')->create();
    Flash::factory()->forUser($user)->maintenance()->onDate('2027-01-04')->create();
    Flash::factory()->forUser($user)->raceCommittee()->onDate('2027-01-05')->create();
    Flash::factory()->forUser($user)->raceCommittee()->onDate('2027-01-06')->create();

    $this->actingAs($user);
    $page = visit('/logbook');

    e2eSubmitNonSailingFlash($page, 'race_committee', '2027-01-07');

    $page->assertVisible('#toast-container .alert-warning');
    $page->assertSeeIn('#toast-container', 'non-sailing');
});

it('counts the first 5 non-sailing days toward awards but not the 6th', function () {
    $user = User::factory()->create();

    Flash::factory()->forUser($user)->sailing()->onDate('2027-01-01')->create(['event_type' => 'regatta']);
    Flash::factory()->forUser($user)->sailing()->onDate('2027-01-08')->create(['event_type' => 'practice']);

    for ($i = 2; $i <= 7; $i++) {
        Flash::factory()->forUser($user)->maintenance()->onDate("2027-01-0{$i}")->create();
    }

    $this->actingAs($user);
    $page = visit('/logbook');

    // The qualifying-day breakdown is the specific assertion; a bare '7' would
    // match dates/ids elsewhere on the page.
    $page->assertSee('2 sailing + 5 non-sailing');
});
