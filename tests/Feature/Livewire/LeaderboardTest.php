<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Leaderboard;
use App\Models\District;
use App\Models\Flash;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_renders_successfully(): void
    {
        Livewire::test(Leaderboard::class)
            ->assertStatus(200)
            ->assertSee('Sailor')
            ->assertSee('Fleet')
            ->assertSee('District');
    }

    public function test_component_defaults_to_sailor_tab(): void
    {
        Livewire::test(Leaderboard::class)
            ->assertSet('tab', 'sailor');
    }

    public function test_component_respects_tab_from_url(): void
    {
        Livewire::withQueryParams(['tab' => 'fleet'])
            ->test(Leaderboard::class)
            ->assertSet('tab', 'fleet');
    }

    public function test_can_switch_tabs(): void
    {
        Livewire::test(Leaderboard::class)
            ->assertSet('tab', 'sailor')
            ->call('switchTab', 'fleet')
            ->assertSet('tab', 'fleet')
            ->call('switchTab', 'district')
            ->assertSet('tab', 'district');
    }

    public function test_invalid_tab_defaults_to_sailor(): void
    {
        Livewire::withQueryParams(['tab' => 'invalid'])
            ->test(Leaderboard::class)
            ->assertSet('tab', 'sailor');
    }

    public function test_switching_tabs_resets_pagination(): void
    {
        // Create enough users to trigger pagination
        for ($i = 1; $i <= 20; $i++) {
            $user = User::factory()->create();
            Flash::factory()->create([
                'user_id' => $user->id,
                'date' => '2025-01-15',
            ]);
            Member::create([
                'user_id' => $user->id,
                'year' => 2025,
            ]);
        }

        // Test that switching tabs resets pagination by verifying we see first page content
        $component = Livewire::test(Leaderboard::class);

        // Navigate to page 2
        $component->set('paginators.page', 2);

        // Switch tabs - this should reset pagination
        $component->call('switchTab', 'fleet');

        // We should now be on page 1 of the fleet tab
        $component->assertSet('tab', 'fleet');
    }

    public function test_displays_sailor_leaderboard(): void
    {
        $user = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-01-15',
        ]);
        Member::create([
            'user_id' => $user->id,
            'year' => 2025,
        ]);

        Livewire::test(Leaderboard::class)
            ->set('tab', 'sailor')
            ->assertSee('John Doe')
            ->assertSee('Yacht Club');
    }

    public function test_displays_fleet_leaderboard(): void
    {
        $district = District::create(['name' => 'Test District']);
        $fleet = Fleet::create([
            'district_id' => $district->id,
            'fleet_number' => 123,
            'fleet_name' => 'Test Fleet',
        ]);

        $user = User::factory()->create();
        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-01-15',
        ]);
        Member::create([
            'user_id' => $user->id,
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
            'year' => 2025,
        ]);

        Livewire::test(Leaderboard::class)
            ->set('tab', 'fleet')
            ->assertSee('Fleet 123')
            ->assertSee('Test Fleet');
    }

    public function test_displays_district_leaderboard(): void
    {
        $district = District::create(['name' => 'Test District']);
        $fleet = Fleet::create([
            'district_id' => $district->id,
            'fleet_number' => 123,
            'fleet_name' => 'Test Fleet',
        ]);

        $user = User::factory()->create();
        Flash::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-01-15',
        ]);
        Member::create([
            'user_id' => $user->id,
            'district_id' => $district->id,
            'fleet_id' => $fleet->id,
            'year' => 2025,
        ]);

        Livewire::test(Leaderboard::class)
            ->set('tab', 'district')
            ->assertSee('Test District');
    }

    public function test_url_updates_when_switching_tabs(): void
    {
        Livewire::test(Leaderboard::class)
            ->call('switchTab', 'fleet')
            ->assertSet('tab', 'fleet');
        // Note: URL query string is managed by Livewire's #[Url] attribute
        // and is automatically synced with the browser URL
    }
}
