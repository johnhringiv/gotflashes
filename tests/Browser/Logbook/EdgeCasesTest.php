<?php

use App\Models\Flash;
use App\Models\User;

beforeEach(function () {
    $this->travelTo(frozenJanuary());
});

it('shows custom 404 page for nonexistent routes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/this-page-does-not-exist');
    $page->assertSee('404');
});

it('edit then cancel preserves original data', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Flash::factory()->sailing()->forUser($user)->onDate(testDate(10))->create([
        'event_type' => 'regatta',
        'location' => 'Original Lake',
    ]);

    $page = visit('/logbook');
    $page->assertSee('Original Lake');

    // Open edit modal
    $page->click('Edit');
    $page->assertVisible('.modal.modal-open');

    // Change the location in the modal
    $page->script("
        const modal = document.querySelector('.modal.modal-open');
        const input = modal.querySelector('input[wire\\\\:model\\\\.live\\\\.blur=\"location\"]');
        if (input) {
            const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
            setter.call(input, 'Changed Lake');
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    ");

    // Cancel instead of saving
    $page->click('.modal.modal-open .btn-error');
    // Original data should be preserved
    $page->assertSee('Original Lake');
    $page->assertDontSee('Changed Lake');
});

it('logbook paginates at 15 entries', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create 18 flashes across different dates
    for ($i = 1; $i <= 15; $i++) {
        Flash::factory()->sailing()->forUser($user)->onDate(testDate($i, 12, -1))->create();
    }
    for ($i = 1; $i <= 3; $i++) {
        Flash::factory()->sailing()->forUser($user)->onDate(testDate($i))->create();
    }

    $page = visit('/logbook');

    // 15 flash cards + ProgressCard + FlashForm container = 17 total .card elements on page 1
    // Verify pagination is active by checking we don't see all 18 flash entries
    $page->assertScript("document.querySelectorAll('.card.bg-base-100.shadow').length <= 17", true);
    // And that there are pagination links
    $page->assertScript("document.querySelectorAll('[wire\\\\:click*=\"nextPage\"], [wire\\\\:click*=\"gotoPage\"]').length > 0", true);
});

it('rejects concurrent duplicate date submission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/logbook');
    $date = testDate(10);

    // Submit a flash via Livewire
    $page->script("
        const formEl = document.querySelector('#activity_type').closest('[wire\\\\:id]');
        const wireId = formEl.getAttribute('wire:id');
        const comp = Livewire.find(wireId);
        comp.set('dates', ['{$date}']);
        comp.set('activity_type', 'sailing');
        comp.set('event_type', 'practice');
        comp.call('save');
    ");
    $page->wait(2);

    // Try to submit the same date again
    $page->script("
        const formEl = document.querySelector('#activity_type').closest('[wire\\\\:id]');
        const wireId = formEl.getAttribute('wire:id');
        const comp = Livewire.find(wireId);
        comp.set('dates', ['{$date}']);
        comp.set('activity_type', 'sailing');
        comp.set('event_type', 'regatta');
        comp.call('save');
    ");
    // Should show error about duplicate date
    $page->assertSee('already');

    // Only one flash should exist in DB
    expect($user->flashes()->count())->toBe(1);
});
