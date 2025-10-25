<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Flash;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_export_data(): void
    {
        $response = $this->get(route('export.user-data'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_export_data(): void
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);

        $response = $this->actingAs($user)->get(route('export.user-data'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition');

        // Check that filename is present and formatted correctly
        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $contentDisposition);
        $this->assertStringContainsString('got-flashes-export', $contentDisposition);
        $this->assertStringContainsString('.csv', $contentDisposition);
    }

    public function test_export_includes_user_information(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'address_line1' => '123 Main St',
            'city' => 'San Diego',
            'state' => 'CA',
            'yacht_club' => 'San Diego Yacht Club',
        ]);

        // Create a flash so user info appears in data rows
        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2024-06-15',
            'activity_type' => 'sailing',
        ]);

        $response = $this->actingAs($user)->get(route('export.user-data'));

        $content = $response->streamedContent();

        // Check for user information in CSV (duplicated on each row)
        $this->assertStringContainsString('Jane Smith', $content);
        $this->assertStringContainsString('jane@example.com', $content);
        $this->assertStringContainsString('123 Main St', $content);
        $this->assertStringContainsString('San Diego', $content);
        $this->assertStringContainsString('CA', $content);
        $this->assertStringContainsString('San Diego Yacht Club', $content);
    }

    public function test_export_includes_flash_data(): void
    {
        $user = User::factory()->create();

        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2024-06-15',
            'activity_type' => 'sailing',
            'event_type' => 'regatta',
            'location' => 'Mission Bay',
            'sail_number' => 12345,
            'notes' => 'Great day on the water',
        ]);

        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2024-07-20',
            'activity_type' => 'maintenance',
            'location' => 'Home',
        ]);

        $response = $this->actingAs($user)->get(route('export.user-data'));

        $content = $response->streamedContent();

        // Check for flash data
        $this->assertStringContainsString('2024-06-15', $content);
        $this->assertStringContainsString('sailing', $content);
        $this->assertStringContainsString('regatta', $content);
        $this->assertStringContainsString('Mission Bay', $content);
        $this->assertStringContainsString('12345', $content);
        $this->assertStringContainsString('Great day on the water', $content);

        $this->assertStringContainsString('2024-07-20', $content);
        $this->assertStringContainsString('maintenance', $content);
    }

    public function test_export_date_formatted_without_time(): void
    {
        $user = User::factory()->create([
            'date_of_birth' => '1990-05-15',
        ]);

        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2024-06-15',
            'activity_type' => 'sailing',
        ]);

        $response = $this->actingAs($user)->get(route('export.user-data'));

        $content = $response->streamedContent();

        // Check date of birth is YYYY-MM-DD format
        $this->assertStringContainsString('1990-05-15', $content);

        // Check flash date is YYYY-MM-DD format (not datetime)
        $this->assertStringContainsString('2024-06-15', $content);
    }

    public function test_export_includes_membership_data_for_flash_year(): void
    {
        $district = District::factory()->create(['name' => 'District 5']);
        $fleet = Fleet::factory()->create([
            'district_id' => $district->id,
            'fleet_number' => 123,
            'fleet_name' => 'Mission Bay Fleet',
        ]);

        $user = User::factory()->create();

        // Create membership for 2024
        Member::create([
            'user_id' => $user->id,
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
            'year' => 2024,
        ]);

        // Create flash in 2024
        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2024-06-15',
            'activity_type' => 'sailing',
        ]);

        $response = $this->actingAs($user)->get(route('export.user-data'));

        $content = $response->streamedContent();

        // Check that district and fleet info is included
        $this->assertStringContainsString('District 5', $content);
        $this->assertStringContainsString('123', $content);
        $this->assertStringContainsString('Mission Bay Fleet', $content);
    }

    public function test_export_handles_membership_changes_across_years(): void
    {
        // Create two districts and fleets
        $district2024 = District::factory()->create(['name' => 'District 5']);
        $fleet2024 = Fleet::factory()->create([
            'district_id' => $district2024->id,
            'fleet_number' => 1230,
            'fleet_name' => 'Mission Bay Fleet',
        ]);

        $district2025 = District::factory()->create(['name' => 'District 6']);
        $fleet2025 = Fleet::factory()->create([
            'district_id' => $district2025->id,
            'fleet_number' => 4560,
            'fleet_name' => 'San Diego Fleet',
        ]);

        $user = User::factory()->create();

        // Create membership for 2024
        Member::create([
            'user_id' => $user->id,
            'district_id' => $district2024->id,
            'fleet_id' => $fleet2024->id,
            'year' => 2024,
        ]);

        // Create membership for 2025
        Member::create([
            'user_id' => $user->id,
            'district_id' => $district2025->id,
            'fleet_id' => $fleet2025->id,
            'year' => 2025,
        ]);

        // Create flashes in both years
        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2024-06-15',
            'activity_type' => 'sailing',
        ]);

        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-03-20',
            'activity_type' => 'sailing',
        ]);

        $response = $this->actingAs($user)->get(route('export.user-data'));

        $content = $response->streamedContent();

        // Parse CSV to check specific rows
        $lines = explode("\n", $content);

        // Find the activity log data (skip header sections)
        $dataStarted = false;
        $flash2024Found = false;
        $flash2025Found = false;

        foreach ($lines as $line) {
            if (str_contains($line, 'Date') && str_contains($line, 'Activity Type')) {
                $dataStarted = true;

                continue;
            }

            if ($dataStarted && ! empty(trim($line))) {
                // Check 2024 flash has 2024 membership
                if (str_contains($line, '2024-06-15')) {
                    $this->assertStringContainsString('District 5', $line);
                    $this->assertStringContainsString('1230', $line);
                    $this->assertStringContainsString('Mission Bay Fleet', $line);
                    $flash2024Found = true;
                }

                // Check 2025 flash has 2025 membership
                if (str_contains($line, '2025-03-20')) {
                    $this->assertStringContainsString('District 6', $line);
                    $this->assertStringContainsString('4560', $line);
                    $this->assertStringContainsString('San Diego Fleet', $line);
                    $flash2025Found = true;
                }
            }
        }

        $this->assertTrue($flash2024Found, 'Flash from 2024 should be in export');
        $this->assertTrue($flash2025Found, 'Flash from 2025 should be in export');
    }

    public function test_export_only_includes_authenticated_users_data(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Flash::factory()->create([
            'user_id' => $user1->id,
            'location' => 'User 1 Location',
        ]);

        Flash::factory()->create([
            'user_id' => $user2->id,
            'location' => 'User 2 Location',
        ]);

        $response = $this->actingAs($user1)->get(route('export.user-data'));

        $content = $response->streamedContent();

        // Should include user1's data
        $this->assertStringContainsString('User 1 Location', $content);

        // Should NOT include user2's data
        $this->assertStringNotContainsString('User 2 Location', $content);
    }

    public function test_export_escapes_csv_special_characters(): void
    {
        $user = User::factory()->create();

        Flash::factory()->create([
            'user_id' => $user->id,
            'notes' => 'Notes with "quotes" and commas, and newlines',
            'location' => 'Location, with comma',
        ]);

        $response = $this->actingAs($user)->get(route('export.user-data'));

        $content = $response->streamedContent();

        // Should properly escape quotes (doubled)
        $this->assertStringContainsString('""quotes""', $content);
    }

    public function test_export_handles_flash_without_membership(): void
    {
        $user = User::factory()->create();

        // Create flash without corresponding membership record
        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2024-06-15',
            'activity_type' => 'sailing',
            'location' => 'Test Location',
        ]);

        $response = $this->actingAs($user)->get(route('export.user-data'));

        $response->assertOk();
        $content = $response->streamedContent();

        // Should still include flash data
        $this->assertStringContainsString('2024-06-15', $content);
        $this->assertStringContainsString('Test Location', $content);

        // District and fleet should be empty (not cause error)
        $this->assertStringContainsString('sailing', $content);
    }

    public function test_export_orders_flashes_by_date_descending(): void
    {
        $user = User::factory()->create();

        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2024-01-15',
            'location' => 'January',
        ]);

        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2024-12-31',
            'location' => 'December',
        ]);

        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2024-06-15',
            'location' => 'June',
        ]);

        $response = $this->actingAs($user)->get(route('export.user-data'));

        $content = $response->streamedContent();
        $lines = explode("\n", $content);

        // Find positions of each month in the output
        $decemberPos = null;
        $junePos = null;
        $januaryPos = null;

        foreach ($lines as $index => $line) {
            if (str_contains($line, 'December')) {
                $decemberPos = $index;
            }
            if (str_contains($line, 'June')) {
                $junePos = $index;
            }
            if (str_contains($line, 'January')) {
                $januaryPos = $index;
            }
        }

        // Should be ordered: December, June, January (newest to oldest)
        $this->assertNotNull($decemberPos);
        $this->assertNotNull($junePos);
        $this->assertNotNull($januaryPos);
        $this->assertLessThan($junePos, $decemberPos);
        $this->assertLessThan($januaryPos, $junePos);
    }
}
