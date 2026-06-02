<?php

namespace App\Console\Commands;

use App\Models\District;
use App\Models\Flash;
use App\Models\Fleet;
use App\Models\Member;
use App\Models\User;
use App\Services\DateRangeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class E2eSeedCommand extends Command
{
    protected $signature = 'e2e:seed
        {--scenario=base : Scenario to seed (base|fresh-user|many-flashes|admin|tiered|leaderboard|tied-ranks|non-sailing-cap)}
        {--email= : Optional email override}
        {--year= : Optional year override}
        {--reset : Run migrate:fresh --seed first}';

    protected $description = 'Seed test data for Playwright E2E tests';

    private const PASSWORD = 'Password123!';

    public function handle(): int
    {
        if (! $this->getLaravel()->environment('local', 'testing')) {
            $this->error('e2e:seed only runs in local/testing environments.');

            return Command::FAILURE;
        }

        if ($this->option('reset')) {
            $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
        }

        $scenario = $this->option('scenario');
        $year = (int) ($this->option('year') ?: now()->year);

        $handled = match ($scenario) {
            'base' => $this->seedBase($year),
            'fresh-user' => $this->seedFreshUser(),
            'many-flashes' => $this->seedManyFlashes($year),
            'admin' => $this->seedAdmin($year),
            'tiered' => $this->seedTiered($year),
            'leaderboard' => $this->seedLeaderboard($year),
            'tied-ranks' => $this->seedTiedRanks($year),
            'non-sailing-cap' => $this->seedNonSailingCap($year),
            default => false,
        };

        if ($handled === false) {
            $this->error("Unknown scenario: {$scenario}");

            return Command::FAILURE;
        }

        $this->info("E2E seed complete: scenario={$scenario}");

        return Command::SUCCESS;
    }

    private function seedBase(int $year): void
    {
        $district = District::firstOrFail();
        $fleet = Fleet::where('district_id', $district->id)->firstOrFail();

        $this->createCanonicalUser('delivered+regular@resend.dev', 'Reggie', 'Sailor', $district->id, $fleet->id, $year);
        $this->createCanonicalUser('delivered+admin@resend.dev', 'Admin', 'User', $district->id, $fleet->id, $year, isAdmin: true);
        $this->createCanonicalUser('delivered+fresh@resend.dev', 'Fresh', 'Start', null, null, $year);
        $this->createCanonicalUser('delivered+unverified@resend.dev', 'Unver', 'Ified', null, null, $year, verified: false);
    }

    private function seedFreshUser(): void
    {
        User::updateOrCreate(
            ['email' => 'delivered+fresh@resend.dev'],
            [
                'first_name' => 'Fresh',
                'last_name' => 'Start',
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'date_of_birth' => '1990-01-15',
                'gender' => 'prefer_not_to_say',
                'country' => 'USA',
            ]
        );
    }

    private function seedManyFlashes(int $year): void
    {
        $user = User::where('email', 'delivered+regular@resend.dev')->firstOrFail();
        [$minDate, $maxDate] = DateRangeService::getAllowedDateRange();

        $existingCount = $user->flashes()->whereYear('date', $year)->count();
        $needed = max(0, 18 - $existingCount);

        for ($i = 0; $i < $needed; $i++) {
            $date = now()->subDays($i + 1)->format('Y-m-d');
            if ($date < $minDate->format('Y-m-d')) {
                break;
            }

            $exists = $user->flashes()->whereDate('date', $date)->exists();
            if ($exists) {
                continue;
            }

            Flash::create([
                'user_id' => $user->id,
                'date' => $date,
                'activity_type' => 'sailing',
                'event_type' => fake()->randomElement(['regatta', 'club_race', 'practice', 'leisure']),
                'location' => fake()->city(),
            ]);
        }
    }

    private function seedAdmin(int $year): void
    {
        $this->seedBase($year);
        $this->seedTiered($year);

        $admin = User::where('email', 'delivered+admin@resend.dev')->firstOrFail();
        $admin->is_admin = true;
        $admin->save();
    }

    private function seedTiered(int $year): void
    {
        $thresholds = [9, 10, 24, 25, 49, 50];
        $district = District::firstOrFail();
        $fleet = Fleet::where('district_id', $district->id)->firstOrFail();

        foreach ($thresholds as $count) {
            $email = "delivered+tier{$count}@resend.dev";
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => "Tier{$count}",
                    'last_name' => 'User',
                    'password' => Hash::make(self::PASSWORD),
                    'email_verified_at' => now(),
                    'date_of_birth' => '1985-06-15',
                    'gender' => 'prefer_not_to_say',
                    'address_line1' => '123 Tier St',
                    'city' => 'Testville',
                    'state' => 'TX',
                    'zip_code' => '12345',
                    'country' => 'USA',
                ]
            );

            Member::updateOrCreate(
                ['user_id' => $user->id, 'year' => $year],
                ['district_id' => $district->id, 'fleet_id' => $fleet->id]
            );

            $existingCount = $user->flashes()->whereYear('date', $year)->count();

            for ($i = $existingCount; $i < $count; $i++) {
                $date = now()->subDays($i + 1)->format('Y-m-d');
                if (! $user->flashes()->whereDate('date', $date)->exists()) {
                    Flash::create([
                        'user_id' => $user->id,
                        'date' => $date,
                        'activity_type' => 'sailing',
                        'event_type' => 'practice',
                    ]);
                }
            }
        }
    }

    private function seedLeaderboard(int $year): void
    {
        $this->seedBase($year);

        $districts = District::take(3)->get();
        $fleetsMap = [];
        foreach ($districts as $d) {
            $fleetsMap[$d->id] = Fleet::where('district_id', $d->id)->take(2)->get();
        }

        for ($i = 1; $i <= 17; $i++) {
            $email = "delivered+lb{$i}@resend.dev";
            $district = $districts[($i - 1) % 3];
            $fleets = $fleetsMap[$district->id];
            $fleet = $fleets[($i - 1) % $fleets->count()];

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => "Sailor{$i}",
                    'last_name' => 'Leaderboard',
                    'password' => Hash::make(self::PASSWORD),
                    'email_verified_at' => now(),
                    'date_of_birth' => '1990-01-01',
                    'gender' => 'prefer_not_to_say',
                    'address_line1' => '456 LB Ave',
                    'city' => 'Sailtown',
                    'state' => 'FL',
                    'zip_code' => '33101',
                    'country' => 'USA',
                ]
            );

            Member::updateOrCreate(
                ['user_id' => $user->id, 'year' => $year],
                ['district_id' => $district->id, 'fleet_id' => $fleet->id]
            );

            $flashCount = 20 - $i;
            $existingCount = $user->flashes()->whereYear('date', $year)->count();

            for ($j = $existingCount; $j < $flashCount; $j++) {
                $date = now()->subDays($j + 1)->format('Y-m-d');
                if (! $user->flashes()->whereDate('date', $date)->exists()) {
                    Flash::create([
                        'user_id' => $user->id,
                        'date' => $date,
                        'activity_type' => 'sailing',
                        'event_type' => 'club_race',
                    ]);
                }
            }
        }
    }

    private function seedTiedRanks(int $year): void
    {
        $this->seedBase($year);
        $district = District::firstOrFail();
        $fleet = Fleet::where('district_id', $district->id)->firstOrFail();

        // Pair 1: same qualifying total (25), different sailing counts
        // User A: 25 sailing + 0 non-sailing = 25 qualifying (25 sailing)
        // User B: 20 sailing + 5 non-sailing = 25 qualifying (20 sailing)
        // A should rank higher (more sailing days)
        $userA = $this->createCanonicalUser('delivered+tieA@resend.dev', 'Alpha', 'Tester', $district->id, $fleet->id, $year);
        $userB = $this->createCanonicalUser('delivered+tieB@resend.dev', 'Bravo', 'Tester', $district->id, $fleet->id, $year);

        $this->createFlashes($userA, 25, 0, $year);
        $this->createFlashes($userB, 20, 5, $year);

        // Pair 2: same qualifying total (10), same sailing count (10), different first entry
        // User C: first entry 30 days ago
        // User D: first entry 10 days ago
        // C should rank higher (earlier first entry)
        $userC = $this->createCanonicalUser('delivered+tieC@resend.dev', 'Anderson', 'Tietest', $district->id, $fleet->id, $year);
        $userD = $this->createCanonicalUser('delivered+tieD@resend.dev', 'Brown', 'Tietest', $district->id, $fleet->id, $year);

        $this->createFlashesWithOffset($userC, 10, $year, 30);
        $this->createFlashesWithOffset($userD, 10, $year, 10);
    }

    private function seedNonSailingCap(int $year): void
    {
        $user = User::where('email', 'delivered+regular@resend.dev')->first();
        if (! $user) {
            $district = District::firstOrFail();
            $fleet = Fleet::where('district_id', $district->id)->firstOrFail();
            $user = $this->createCanonicalUser('delivered+regular@resend.dev', 'Reggie', 'Sailor', $district->id, $fleet->id, $year);
        }

        // Create 5 sailing days
        $this->createFlashes($user, 5, 0, $year);

        // Create exactly 5 non-sailing days (3 maintenance + 2 race_committee)

        $maintenanceNeeded = max(0, 3 - $user->flashes()->whereYear('date', $year)->where('activity_type', 'maintenance')->count());
        $rcNeeded = max(0, 2 - $user->flashes()->whereYear('date', $year)->where('activity_type', 'race_committee')->count());

        $dayOffset = $user->flashes()->whereYear('date', $year)->count();

        for ($i = 0; $i < $maintenanceNeeded; $i++) {
            $date = now()->subDays($dayOffset + $i + 1)->format('Y-m-d');
            if (! $user->flashes()->whereDate('date', $date)->exists()) {
                Flash::create([
                    'user_id' => $user->id,
                    'date' => $date,
                    'activity_type' => 'maintenance',
                    'event_type' => null,
                ]);
            }
        }

        $dayOffset += $maintenanceNeeded;

        for ($i = 0; $i < $rcNeeded; $i++) {
            $date = now()->subDays($dayOffset + $i + 1)->format('Y-m-d');
            if (! $user->flashes()->whereDate('date', $date)->exists()) {
                Flash::create([
                    'user_id' => $user->id,
                    'date' => $date,
                    'activity_type' => 'race_committee',
                    'event_type' => null,
                ]);
            }
        }
    }

    private function createCanonicalUser(
        string $email,
        string $firstName,
        string $lastName,
        ?int $districtId,
        ?int $fleetId,
        int $year,
        bool $isAdmin = false,
        bool $verified = true,
    ): User {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => $verified ? now() : null,
                'date_of_birth' => '1990-01-15',
                'gender' => 'prefer_not_to_say',
                'address_line1' => '123 Test St',
                'city' => 'Testville',
                'state' => 'TX',
                'zip_code' => '12345',
                'country' => 'USA',
                'yacht_club' => 'Test Yacht Club',
            ]
        );

        // is_admin is not in $fillable, so set it directly
        if ($isAdmin) {
            $user->is_admin = true;
            $user->save();
        }

        Member::updateOrCreate(
            ['user_id' => $user->id, 'year' => $year],
            ['district_id' => $districtId, 'fleet_id' => $fleetId]
        );

        return $user;
    }

    private function createFlashes(User $user, int $sailingCount, int $nonSailingCount, int $year): void
    {
        $dayOffset = $user->flashes()->whereYear('date', $year)->count();

        for ($i = 0; $i < $sailingCount; $i++) {
            $date = now()->subDays($dayOffset + $i + 1)->format('Y-m-d');
            if (! $user->flashes()->whereDate('date', $date)->exists()) {
                Flash::create([
                    'user_id' => $user->id,
                    'date' => $date,
                    'activity_type' => 'sailing',
                    'event_type' => 'practice',
                ]);
            }
        }

        $dayOffset += $sailingCount;

        for ($i = 0; $i < $nonSailingCount; $i++) {
            $date = now()->subDays($dayOffset + $i + 1)->format('Y-m-d');
            if (! $user->flashes()->whereDate('date', $date)->exists()) {
                Flash::create([
                    'user_id' => $user->id,
                    'date' => $date,
                    'activity_type' => 'maintenance',
                    'event_type' => null,
                ]);
            }
        }
    }

    private function createFlashesWithOffset(User $user, int $count, int $year, int $startDaysAgo): void
    {
        for ($i = 0; $i < $count; $i++) {
            $date = now()->subDays($startDaysAgo + $i)->format('Y-m-d');
            if (! $user->flashes()->whereDate('date', $date)->exists()) {
                Flash::create([
                    'user_id' => $user->id,
                    'date' => $date,
                    'activity_type' => 'sailing',
                    'event_type' => 'practice',
                ]);
            }
        }
    }
}
