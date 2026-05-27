<?php

use App\Models\District;
use App\Models\Flash;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2027-01-15 12:00:00'));

    $this->admin = User::factory()->create([

        'first_name' => 'CSV',
        'last_name' => 'Admin',
    ]);
    $this->admin->is_admin = true;
    $this->admin->save();

    $district = District::first();
    $fleet = Fleet::where('district_id', District::first()->id)->first();

    $this->sailor = User::factory()->create([

        'first_name' => 'CSV',
        'last_name' => 'Sailor',
    ]);
    Member::create([
        'user_id' => $this->sailor->id,
        'district_id' => $district->id,
        'fleet_id' => $fleet->id,
        'year' => 2027,
    ]);
    for ($i = 1; $i <= 10; $i++) {
        $day = str_pad($i, 2, '0', STR_PAD_LEFT);
        Flash::factory()->sailing()->forUser($this->sailor)->onDate("2027-01-{$day}")->create();
    }
});

it('downloads CSV via Export', function () {
    $this->actingAs($this->admin);

    $page = visit('/admin/fulfillment');

    // Select an award
    $page->click('table tbody tr:first-child input[type="checkbox"]');
    $page->wait(1);

    // Click "Export CSV" in bulk action bar
    $page->click('Export CSV');
    $page->wait(1);

    // Confirmation modal should appear
    $page->assertSee('Export to CSV');
    $page->assertSee('CSV file will include names');

    // Click export in modal
    $page->click('.modal-action button.btn-primary, dialog button.btn-primary');
    $page->wait(2);

    // The CSV export triggers a streamDownload response.
    // In browser tests, we verify the modal appeared and the button was clickable.
    // The actual download is handled by the Livewire component's exportToCsv method,
    // which is tested at the HTTP level in feature tests.
});
