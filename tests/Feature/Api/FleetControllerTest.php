<?php

namespace Tests\Feature\Api;

use App\Models\District;
use App\Models\Fleet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_districts_and_fleets_endpoint_returns_json(): void
    {
        $response = $this->get('/api/districts-and-fleets');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_districts_and_fleets_returns_correct_structure(): void
    {
        // Create test data
        $district = District::factory()->create(['name' => 'Test District']);
        $fleet = Fleet::factory()->create([
            'district_id' => $district->id,
            'fleet_number' => 123,
            'fleet_name' => 'Test Fleet',
        ]);

        $response = $this->get('/api/districts-and-fleets');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'districts' => [
                    '*' => ['id', 'name'],
                ],
                'fleets' => [
                    '*' => ['id', 'fleet_number', 'fleet_name', 'district_id', 'district_name'],
                ],
            ]);
    }

    public function test_fleets_include_district_name(): void
    {
        $district = District::factory()->create(['name' => 'Pacific District']);
        $fleet = Fleet::factory()->create([
            'district_id' => $district->id,
            'fleet_number' => 9999,
            'fleet_name' => 'San Diego Fleet',
        ]);

        $response = $this->get('/api/districts-and-fleets');

        $response->assertStatus(200);

        $fleets = $response->json('fleets');

        // Find our test fleet in the response
        $testFleet = collect($fleets)->firstWhere('fleet_number', 9999);

        $this->assertNotNull($testFleet, 'Test fleet should be in response');
        $this->assertEquals('Pacific District', $testFleet['district_name']);
        $this->assertEquals('San Diego Fleet', $testFleet['fleet_name']);
        $this->assertEquals(9999, $testFleet['fleet_number']);
    }

    public function test_endpoint_returns_empty_arrays_when_no_data(): void
    {
        $response = $this->get('/api/districts-and-fleets');

        $response->assertStatus(200)
            ->assertJson([
                'districts' => [],
                'fleets' => [],
            ]);
    }

    public function test_endpoint_has_cache_headers(): void
    {
        $response = $this->get('/api/districts-and-fleets');

        $response->assertStatus(200)
            ->assertHeader('Cache-Control');
    }
}
