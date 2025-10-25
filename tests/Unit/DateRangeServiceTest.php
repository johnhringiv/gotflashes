<?php

namespace Tests\Unit;

use App\Services\DateRangeService;
use Carbon\Carbon;
use Tests\TestCase;

class DateRangeServiceTest extends TestCase
{
    /**
     * Test that grace period does NOT apply in January of START_YEAR.
     *
     * Scenario: App launches in 2026. In January 2026, users should only
     * be able to enter 2026 dates (no grace period for 2025).
     */
    public function test_no_grace_period_in_january_of_start_year(): void
    {
        // Set START_YEAR to 2026
        putenv('START_YEAR=2026');

        // Test January 15, 2026 (the launch year)
        $now = Carbon::parse('2026-01-15');

        [$minDate, $maxDate] = DateRangeService::getAllowedDateRange($now);

        // Should NOT allow previous year (2025)
        $this->assertEquals('2026-01-01', $minDate->format('Y-m-d'), 'Grace period should NOT apply in January of START_YEAR');
        $this->assertEquals('2026-01-16', $maxDate->format('Y-m-d'), 'Max date should be tomorrow');
    }

    /**
     * Test that grace period DOES apply in January after START_YEAR.
     *
     * Scenario: In January 2027, users should be able to enter 2026 dates
     * (grace period active).
     */
    public function test_grace_period_applies_in_january_after_start_year(): void
    {
        // Set START_YEAR to 2026
        putenv('START_YEAR=2026');

        // Test January 15, 2027 (one year after launch)
        $now = Carbon::parse('2027-01-15');

        [$minDate, $maxDate] = DateRangeService::getAllowedDateRange($now);

        // Should allow previous year (2026)
        $this->assertEquals('2026-01-01', $minDate->format('Y-m-d'), 'Grace period should apply in January after START_YEAR');
        $this->assertEquals('2027-01-16', $maxDate->format('Y-m-d'), 'Max date should be tomorrow');
    }

    /**
     * Test that grace period DOES apply in January two years after START_YEAR.
     *
     * Scenario: In January 2028, users should be able to enter 2027 dates
     * (grace period active, but NOT 2026).
     */
    public function test_grace_period_only_allows_previous_year(): void
    {
        // Set START_YEAR to 2026
        putenv('START_YEAR=2026');

        // Test January 15, 2028 (two years after launch)
        $now = Carbon::parse('2028-01-15');

        [$minDate, $maxDate] = DateRangeService::getAllowedDateRange($now);

        // Should allow previous year (2027), but not earlier years
        $this->assertEquals('2027-01-01', $minDate->format('Y-m-d'), 'Grace period should only allow previous year (2027)');
        $this->assertEquals('2028-01-16', $maxDate->format('Y-m-d'), 'Max date should be tomorrow');
    }

    /**
     * Test that grace period does NOT apply in February (regardless of START_YEAR).
     */
    public function test_no_grace_period_in_february(): void
    {
        // Set START_YEAR to 2026
        putenv('START_YEAR=2026');

        // Test February 1, 2027 (day after grace period ends)
        $now = Carbon::parse('2027-02-01');

        [$minDate, $maxDate] = DateRangeService::getAllowedDateRange($now);

        // Should only allow current year (2027)
        $this->assertEquals('2027-01-01', $minDate->format('Y-m-d'), 'No grace period in February');
        $this->assertEquals('2027-02-02', $maxDate->format('Y-m-d'), 'Max date should be tomorrow');
    }

    /**
     * Test that grace period does NOT apply in other months.
     */
    public function test_no_grace_period_in_other_months(): void
    {
        // Set START_YEAR to 2026
        putenv('START_YEAR=2026');

        // Test June 15, 2027
        $now = Carbon::parse('2027-06-15');

        [$minDate, $maxDate] = DateRangeService::getAllowedDateRange($now);

        // Should only allow current year (2027)
        $this->assertEquals('2027-01-01', $minDate->format('Y-m-d'), 'No grace period outside January');
        $this->assertEquals('2027-06-16', $maxDate->format('Y-m-d'), 'Max date should be tomorrow');
    }

    /**
     * Test default START_YEAR value when env var is not set.
     */
    public function test_defaults_to_2026_when_start_year_not_set(): void
    {
        // Clear START_YEAR env var
        putenv('START_YEAR');

        // Test January 15, 2026 (should use default START_YEAR=2026)
        $now = Carbon::parse('2026-01-15');

        [$minDate, $maxDate] = DateRangeService::getAllowedDateRange($now);

        // Should NOT allow previous year (2025) because we're IN the start year
        $this->assertEquals('2026-01-01', $minDate->format('Y-m-d'), 'Should default to START_YEAR=2026');
    }

    /**
     * Test that January 31st of START_YEAR still has no grace period.
     */
    public function test_last_day_of_january_in_start_year_no_grace_period(): void
    {
        // Set START_YEAR to 2026
        putenv('START_YEAR=2026');

        // Test January 31, 2026 (last day of January in launch year)
        $now = Carbon::parse('2026-01-31');

        [$minDate, $maxDate] = DateRangeService::getAllowedDateRange($now);

        // Should NOT allow previous year (2025)
        $this->assertEquals('2026-01-01', $minDate->format('Y-m-d'), 'No grace period even on last day of January in START_YEAR');
        $this->assertEquals('2026-02-01', $maxDate->format('Y-m-d'), 'Max date should be tomorrow');
    }

    /**
     * Test that January 1st after START_YEAR has grace period.
     */
    public function test_first_day_of_january_after_start_year_has_grace_period(): void
    {
        // Set START_YEAR to 2026
        putenv('START_YEAR=2026');

        // Test January 1, 2027 (first day of January after launch year)
        $now = Carbon::parse('2027-01-01');

        [$minDate, $maxDate] = DateRangeService::getAllowedDateRange($now);

        // Should allow previous year (2026) from day 1
        $this->assertEquals('2026-01-01', $minDate->format('Y-m-d'), 'Grace period should activate immediately on Jan 1 after START_YEAR');
        $this->assertEquals('2027-01-02', $maxDate->format('Y-m-d'), 'Max date should be tomorrow');
    }
}
