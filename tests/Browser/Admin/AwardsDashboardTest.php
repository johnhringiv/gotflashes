<?php

use App\Models\AwardFulfillment;
use App\Models\District;
use App\Models\Flash;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2027-01-15 12:00:00'));

    // Create admin user
    $this->admin = User::factory()->create([

        'first_name' => 'Admin',
        'last_name' => 'User',
    ]);
    $this->admin->is_admin = true;
    $this->admin->save();

    $this->district = District::first();
    $this->fleet = Fleet::where('district_id', District::first()->id)->first();

    // Create users at different tiers
    $this->userTier10 = User::factory()->create([

        'first_name' => 'Ten',
        'last_name' => 'Day',
    ]);
    Member::create([
        'user_id' => $this->userTier10->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => 2027,
    ]);
    for ($i = 1; $i <= 10; $i++) {
        $day = str_pad($i, 2, '0', STR_PAD_LEFT);
        Flash::factory()->sailing()->forUser($this->userTier10)->onDate("2027-01-{$day}")->create();
    }

    $this->userTier25 = User::factory()->create([

        'first_name' => 'TwentyFive',
        'last_name' => 'Day',
    ]);
    Member::create([
        'user_id' => $this->userTier25->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => 2027,
    ]);
    // Use January dates only (within grace period and frozen time)
    for ($i = 1; $i <= 15; $i++) {
        $day = str_pad($i, 2, '0', STR_PAD_LEFT);
        Flash::factory()->sailing()->forUser($this->userTier25)->onDate("2027-01-{$day}")->create();
    }
});

it('filters by status=earned', function () {
    $this->actingAs($this->admin);

    $page = visit('/admin/fulfillment');

    $page->select('select[wire\\:model\\.live="statusFilter"]', 'earned');
    // All visible rows should have "Earned" status badge
    $page->assertSee('Earned');
    $page->assertScript("document.querySelector('table tbody')?.textContent.includes('Processing') ?? false", false);
    $page->assertScript("document.querySelector('table tbody')?.textContent.includes('Sent') ?? false", false);
});

it('filters by status=processing', function () {
    // Create a processing fulfillment
    AwardFulfillment::create([
        'user_id' => $this->userTier10->id,
        'year' => 2027,
        'award_tier' => 10,
        'status' => 'processing',
        'updated_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);

    $page = visit('/admin/fulfillment');

    $page->select('select[wire\\:model\\.live="statusFilter"]', 'processing');
    $page->assertSee('Processing');
});

it('filters by status=sent', function () {
    AwardFulfillment::create([
        'user_id' => $this->userTier10->id,
        'year' => 2027,
        'award_tier' => 10,
        'status' => 'sent',
        'updated_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);

    $page = visit('/admin/fulfillment');

    $page->select('select[wire\\:model\\.live="statusFilter"]', 'sent');
    $page->assertSee('Sent');
});

it('filters by tier=10', function () {
    $this->actingAs($this->admin);

    $page = visit('/admin/fulfillment');

    $page->select('select[wire\\:model\\.live="tierFilter"]', '10');
    // Both users qualify for tier 10 (Ten has 10 days, TwentyFive has 15)
    $page->assertSee('Ten');
});

it('searches by participant name', function () {
    $this->actingAs($this->admin);

    $page = visit('/admin/fulfillment');

    $page->fill('input[wire\\:model\\.live\\.debounce\\.300ms="searchQuery"]', 'TwentyFive');
    $page->assertSee('TwentyFive');
    $page->assertDontSee('Ten Day');
});

it('searches by email', function () {
    $this->actingAs($this->admin);

    $page = visit('/admin/fulfillment');

    $page->fill('input[wire\\:model\\.live\\.debounce\\.300ms="searchQuery"]', $this->userTier10->email);
    $page->assertSee('Ten');
});

it('selects all visible awards via master Select All', function () {
    $this->actingAs($this->admin);

    $page = visit('/admin/fulfillment');

    // Click "Select All" button in table header
    $page->click('Select All');
    // Should show selection count in the bulk action bar
    $page->assertSee('awards selected');
});

it('selects all earned awards via scoped button', function () {
    $this->actingAs($this->admin);

    // Visit with earned filter
    $page = visit('/admin/fulfillment');
    $page->select('select[wire\\:model\\.live="statusFilter"]', 'earned');
    // Click "Select All" button
    $page->click('Select All');
    $page->assertSee('awards selected');
});

it('selects all processing awards via scoped button', function () {
    // Create some processing fulfillments
    AwardFulfillment::create([
        'user_id' => $this->userTier10->id,
        'year' => 2027,
        'award_tier' => 10,
        'status' => 'processing',
        'updated_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);

    $page = visit('/admin/fulfillment');
    $page->select('select[wire\\:model\\.live="statusFilter"]', 'processing');
    $page->click('Select All');
    $page->assertSee('awards selected');
});
