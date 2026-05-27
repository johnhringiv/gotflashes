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

    // Create admin
    $this->admin = User::factory()->create([

        'first_name' => 'Bulk',
        'last_name' => 'Admin',
    ]);
    $this->admin->is_admin = true;
    $this->admin->save();

    $this->district = District::first();
    $this->fleet = Fleet::where('district_id', District::first()->id)->first();

    // Create a user with 10 flashes (qualifies for tier 10)
    $this->sailor = User::factory()->create([

        'first_name' => 'Bulk',
        'last_name' => 'Sailor',
    ]);
    Member::create([
        'user_id' => $this->sailor->id,
        'district_id' => $this->district->id,
        'fleet_id' => $this->fleet->id,
        'year' => 2027,
    ]);
    for ($i = 1; $i <= 10; $i++) {
        $day = str_pad($i, 2, '0', STR_PAD_LEFT);
        Flash::factory()->sailing()->forUser($this->sailor)->onDate("2027-01-{$day}")->create();
    }
});

it('marks selected awards as Processing with confirmation', function () {
    $this->actingAs($this->admin);

    $page = visit('/admin/fulfillment');

    // Select the first checkbox
    $page->click('table tbody tr:first-child input[type="checkbox"]');
    $page->wait(1);

    // Click "Mark as Processing" button in bulk action bar
    $page->click('Mark as Processing');
    $page->wait(1);

    // Confirmation modal should appear
    $page->assertSee('Are you sure you want to mark');

    // Confirm the action
    $page->click('.modal-action button.btn-info, dialog button.btn-info');
    $page->wait(2);

    // Should show success message
    $page->assertSee('processing');

    // Verify in database
    $fulfillment = AwardFulfillment::where('user_id', $this->sailor->id)
        ->where('award_tier', 10)
        ->first();
    expect($fulfillment)->not->toBeNull();
    expect($fulfillment->status)->toBe('processing');
});

it('marks selected awards as Sent with confirmation', function () {
    // First set to processing
    AwardFulfillment::create([
        'user_id' => $this->sailor->id,
        'year' => 2027,
        'award_tier' => 10,
        'status' => 'processing',
        'updated_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);

    $page = visit('/admin/fulfillment');

    // Select the checkbox
    $page->click('table tbody tr:first-child input[type="checkbox"]');
    $page->wait(1);

    // Click "Mark as Sent"
    $page->click('Mark as Sent');
    $page->wait(1);

    // Confirmation modal should appear
    $page->assertSee('Are you sure you want to mark');

    // Confirm
    $page->click('.modal-action button.btn-success, dialog button.btn-success');
    $page->wait(2);

    // Verify in database
    $fulfillment = AwardFulfillment::where('user_id', $this->sailor->id)
        ->where('award_tier', 10)
        ->first();
    expect($fulfillment->status)->toBe('sent');
});

it('shows Earned to Sent warning when skipping Processing', function () {
    $this->actingAs($this->admin);

    $page = visit('/admin/fulfillment');

    // Select earned award
    $page->click('table tbody tr:first-child input[type="checkbox"]');
    $page->wait(1);

    // Click "Mark as Sent" (skipping Processing)
    $page->click('Mark as Sent');
    $page->wait(1);

    // Should show warning about skipping Processing
    $page->assertSee('Skipping Processing');
    $page->assertSee('currently Earned');
});

it('shows Downgrade warning when moving Sent back to Processing', function () {
    // Set award to sent
    AwardFulfillment::create([
        'user_id' => $this->sailor->id,
        'year' => 2027,
        'award_tier' => 10,
        'status' => 'sent',
        'updated_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);

    // Visit with "all" status filter to see sent awards
    $page = visit('/admin/fulfillment');
    $page->select('select[wire\\:model\\.live="statusFilter"]', 'all');
    $page->wait(1);

    // Select the sent award
    $page->click('table tbody tr:first-child input[type="checkbox"]');
    $page->wait(1);

    // Click "Mark as Processing" (downgrade)
    $page->click('Mark as Processing');
    $page->wait(1);

    // Should show downgrade warning
    $page->assertSee('Downgrading Status');
    $page->assertSee('reverted back to processing');
});

it('removes fulfillment records (resets to Earned)', function () {
    AwardFulfillment::create([
        'user_id' => $this->sailor->id,
        'year' => 2027,
        'award_tier' => 10,
        'status' => 'processing',
        'updated_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);

    $page = visit('/admin/fulfillment');

    // Select the award
    $page->click('table tbody tr:first-child input[type="checkbox"]');
    $page->wait(1);

    // Click "Reset to Earned"
    $page->click('Reset to Earned');
    $page->wait(1);

    // Confirmation modal should appear
    $page->assertSee('Reset Award Status');

    // Check the confirmation checkbox
    $page->click('.checkbox-warning');
    $page->wait(1);

    // Click the reset button
    $page->click('.modal-action button.btn-warning, dialog button.btn-warning');
    $page->wait(2);

    // Verify fulfillment was deleted
    expect(AwardFulfillment::where('user_id', $this->sailor->id)
        ->where('award_tier', 10)
        ->exists())->toBeFalse();
});

it('blocks CSV export when no rows selected', function () {
    $this->actingAs($this->admin);

    $page = visit('/admin/fulfillment');

    // The Export CSV button should not be visible when no awards are selected
    // (bulk action bar only appears when selectedCount > 0)
    $page->assertDontSee('awards selected');
    $page->assertMissing('.fixed.bottom-0 button');
});
