<?php

use App\Models\District;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->district = District::where('name', '!=', District::NONE_NAME)->firstOrFail();
    $this->fleet = Fleet::where('district_id', $this->district->id)->firstOrFail();
    Member::create([
        'user_id' => $this->user->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => now()->year,
    ]);
    $this->actingAs($this->user);
});

describe('district/fleet controls on profile', function () {
    it('server-renders options and enhances both selects with comboboxes', function () {
        $page = visit('/profile');
        // Every district is a server-rendered option on the hidden select
        $page->assertScript(
            "document.querySelectorAll('#district-select option').length",
            District::count() + 1 // + placeholder
        );
        // Both native selects hidden behind combobox inputs showing the labels
        $page->assertScript("document.getElementById('district-select').hidden", true);
        $page->assertScript("document.getElementById('fleet-select').hidden", true);
        $page->assertScript(
            "document.getElementById('district-select-input').value",
            $this->district->name
        );
        $page->assertScript(
            "document.getElementById('fleet-select-input').value",
            "Fleet {$this->fleet->fleet_number} - {$this->fleet->fleet_name}"
        );
    });

    it('clearing the district widens the fleet list to every fleet', function () {
        $page = visit('/profile');
        $page->script("document.getElementById('district-select')._combobox.clear()");
        $page->script("document.getElementById('fleet-select-input').click()");
        $page->assertScript(
            'document.querySelectorAll(".combobox-option").length',
            Fleet::count()
        );
    });

    it('opens the fleet listbox on click with the district-filtered rows', function () {
        $page = visit('/profile');
        $page->script("document.getElementById('fleet-select-input').click()");
        $expected = Fleet::where('district_id', $this->district->id)->count() + 1; // + None
        $page->assertScript('document.querySelectorAll(".combobox-option").length', $expected);
        // The saved fleet is marked selected and highlighted
        $page->assertScript(
            'document.querySelector(".combobox-option.selected.active")?.dataset.value',
            (string) $this->fleet->id
        );
    });

    it('filters rows as the user types', function () {
        $page = visit('/profile');
        $page->script(selectNativeJs('district-select', District::noneId())); // widen to all fleets
        $page->script("document.getElementById('fleet-select-input').click()");
        $needle = strtolower(substr($this->fleet->fleet_name, 0, 6));
        $page->script("
            const input = document.getElementById('fleet-select-input');
            input.value = '{$needle}';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        ");
        $expected = Fleet::where('fleet_name', 'like', "%{$needle}%")->count();
        $page->assertScript('document.querySelectorAll(".combobox-option").length', $expected);
    });

    it('supports keyboard: arrows move, Enter picks, Escape closes', function () {
        $page = visit('/profile');
        $page->script("document.getElementById('fleet-select-input').click()");
        $page->script("
            const input = document.getElementById('fleet-select-input');
            input.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
            input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
        ");
        // Listbox closed and a value committed
        $page->assertNotPresent('.combobox-listbox');
        $page->assertScript("document.getElementById('fleet-select').value !== ''", true);

        $page->script("document.getElementById('fleet-select-input').click()");
        $page->assertPresent('.combobox-listbox');
        $page->script("
            document.getElementById('fleet-select-input')
                .dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        ");
        $page->assertNotPresent('.combobox-listbox');
    });

    it('light-dismisses and reverts abandoned typing', function () {
        $page = visit('/profile');
        $page->script("document.getElementById('fleet-select-input').click()");
        $page->script("
            const input = document.getElementById('fleet-select-input');
            input.value = 'zzz-no-such-fleet';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        ");
        $page->assertSee('No matches');
        $page->script("document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }))");
        $page->assertNotPresent('.combobox-listbox');
        // Selection untouched, label restored
        $page->assertScript("document.getElementById('fleet-select').value", (string) $this->fleet->id);
        $page->assertScript(
            "document.getElementById('fleet-select-input').value",
            "Fleet {$this->fleet->fleet_number} - {$this->fleet->fleet_name}"
        );
    });
});

describe('district/fleet controls on mobile', function () {
    // Real taps in a touch context (hasTouch) — these hit the pointerType-aware
    // paths that plain click() cannot reach. Drag-scroll gestures stay in the
    // standalone CDP script (scratchpad/mobile-combobox.mjs); the plugin has no
    // gesture API.
    it('opening tap browses with the virtual keyboard suppressed', function () {
        $page = visit('/profile')->on()->mobile()->assertPresent('#fleet-select-input');
        $page->page()->locator('#fleet-select-input')->tap();
        $page->assertPresent('.combobox-listbox');
        $page->assertScript("document.getElementById('fleet-select-input').inputMode", 'none');
    });

    it('tapping a row picks it and closes the list', function () {
        $page = visit('/profile')->on()->mobile()->assertPresent('#fleet-select-input');
        $page->page()->locator('#fleet-select-input')->tap();
        $page->assertPresent('.combobox-listbox');
        $noneId = Fleet::noneId();
        $page->page()->locator(".combobox-option[data-value=\"{$noneId}\"]")->tap();
        $page->assertNotPresent('.combobox-listbox');
        $page->assertScript("document.getElementById('fleet-select').value", (string) $noneId);
    });

    it('a second tap on the input opts into typing', function () {
        $page = visit('/profile')->on()->mobile()->assertPresent('#fleet-select-input');
        $page->page()->locator('#fleet-select-input')->tap();
        $page->assertPresent('.combobox-listbox');
        $page->page()->locator('#fleet-select-input')->tap();
        $page->assertScript("document.getElementById('fleet-select-input').inputMode", 'text');
        $page->assertPresent('.combobox-listbox'); // opting in never closes the list
    });
});

describe('district/fleet controls on registration', function () {
    it('shows placeholders for both selects on the registration form', function () {
        // Need to be anonymous to visit /register (guest middleware)
        auth()->logout();
        $page = visit('/register');
        $page->assertScript("document.getElementById('district-select').value", '');
        $page->assertScript("document.getElementById('district-select-input').placeholder", 'Select district...');
        $page->assertScript("document.getElementById('district-select-input').value", '');
        $page->assertScript("document.getElementById('fleet-select-input').placeholder", 'Select fleet...');
        $page->assertScript("document.getElementById('fleet-select-input').value", '');
    });
});
