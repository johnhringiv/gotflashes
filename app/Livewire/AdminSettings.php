<?php

namespace App\Livewire;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Admin page for per-year community settings — currently the annual
 * community sailing-days goal shown on the public /stats page.
 */
class AdminSettings extends AdminComponent
{
    public ?int $goal = null;

    public function mount(): void
    {
        parent::mount();
        $this->loadGoal();
    }

    public function updatedSelectedYear(): void
    {
        $this->loadGoal();
    }

    public function save(): void
    {
        $this->validate([
            'goal' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);

        Setting::set(
            "community_goal_{$this->selectedYear}",
            $this->goal !== null ? (string) $this->goal : null
        );

        // The public stats page caches per year; show the new goal immediately
        CommunityStats::clearCache($this->selectedYear);

        $this->dispatch('toast', type: 'success', message: "Community goal for {$this->selectedYear} saved.");
    }

    public function render()
    {
        $prior = $this->selectedYear - 1;
        $historical = config('community.historical_totals', []);

        return view('livewire.admin-settings', [
            'availableYears' => $this->getAvailableYearsWithCurrent(),
            'currentTotal' => $this->getQualifyingTotal($this->selectedYear),
            'priorYear' => $prior,
            'priorTotal' => (int) ($historical[$prior] ?? $this->getQualifyingTotal($prior)),
            'priorIsHistorical' => isset($historical[$prior]),
        ]);
    }

    private function loadGoal(): void
    {
        $goal = Setting::get("community_goal_{$this->selectedYear}");
        $this->goal = $goal !== null ? (int) $goal : null;
    }

    /**
     * @return array<int>
     */
    private function getAvailableYearsWithCurrent(): array
    {
        return collect($this->getAvailableYears())
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();
    }

    /**
     * Community-wide qualifying total for the year (sailing days plus up to
     * 5 non-sailing days per sailor) — same math as the leaderboard.
     */
    private function getQualifyingTotal(int $year): int
    {
        $row = DB::query()
            ->fromSub(
                DB::table('flashes')
                    ->select([
                        'user_id',
                        DB::raw("SUM(CASE WHEN activity_type = 'sailing' THEN 1 ELSE 0 END) as sailing_count"),
                        DB::raw("SUM(CASE WHEN activity_type IN ('maintenance', 'race_committee') THEN 1 ELSE 0 END) as non_sailing_count"),
                    ])
                    ->whereRaw("strftime('%Y', date) = ?", [(string) $year])
                    ->groupBy('user_id'),
                'user_flashes'
            )
            ->selectRaw('COALESCE(SUM(sailing_count + CASE WHEN non_sailing_count > 5 THEN 5 ELSE non_sailing_count END), 0) as total')
            ->first();

        return (int) ($row->total ?? 0);
    }
}
