<?php

use App\Models\Flash;
use App\Models\User;

beforeEach(function () {
    $this->travelTo(frozenJanuary());
    $this->user = User::factory()->create([]);
    $this->actingAs($this->user);
});

describe('Create mode', function () {
    it('sailing_type starts disabled when activity_type is empty', function () {
        $page = visit('/logbook');
        $page->assertDisabled('#sailing_type');
    });

    it('choosing sailing enables sailing_type', function () {
        $page = visit('/logbook');
        $page->select('#activity_type', 'sailing');
        $page->assertEnabled('#sailing_type');
    });

    it('choosing maintenance disables and clears sailing_type', function () {
        $page = visit('/logbook');
        $page->select('#activity_type', 'sailing');
        $page->assertEnabled('#sailing_type');

        $page->select('#activity_type', 'maintenance');
        $page->assertDisabled('#sailing_type');
    });

    it('choosing race_committee disables sailing_type', function () {
        $page = visit('/logbook');
        $page->select('#activity_type', 'sailing');
        $page->select('#activity_type', 'race_committee');
        $page->assertDisabled('#sailing_type');
    });

    it('sail_number numeric filter strips non-numeric characters', function () {
        $page = visit('/logbook');
        $page->type('#sail_number', 'abc123def');
        $page->assertScript(
            'document.querySelector("#sail_number").value',
            '123'
        );
    });
});

describe('Edit modal', function () {
    it('reinitializes form JS in edit modal', function () {
        Flash::factory()->sailing()->forUser($this->user)->onDate(testDate(10))->create();

        $page = visit('/logbook');
        $page->click('Edit');
        $page->assertVisible('.modal.modal-open');

        $page->select('#activity_type_edit', 'maintenance');
        $page->assertDisabled('#sailing_type_edit');

        $page->select('#activity_type_edit', 'sailing');
        $page->assertEnabled('#sailing_type_edit');
    });

    it('edit modal form fields are pre-filled correctly', function () {
        Flash::factory()->sailing()->forUser($this->user)->onDate(testDate(10))->create([
            'event_type' => 'regatta',
            'location' => 'Test Lake',
        ]);

        $page = visit('/logbook');
        $page->click('Edit');
        $page->assertVisible('.modal.modal-open');
        $page->assertVisible('#activity_type_edit');
    });
});
