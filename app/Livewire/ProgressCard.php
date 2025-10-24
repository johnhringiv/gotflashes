<?php

namespace App\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class ProgressCard extends Component
{
    public int $currentYear;

    public function mount()
    {
        $this->currentYear = now()->year;
    }

    #[On('flash-saved')]
    #[On('flash-deleted')]
    public function refresh()
    {
        // This will cause the component to re-render with fresh data
    }

    public function render()
    {
        $user = auth()->user();
        $stats = $user->flashStatsForYear($this->currentYear);

        // Award tier milestones
        $milestones = [10, 25, 50];
        $nextMilestone = null;
        $earnedAwards = [];

        foreach ($milestones as $milestone) {
            if ($stats->total >= $milestone) {
                $earnedAwards[] = $milestone;
            } elseif ($nextMilestone === null) {
                $nextMilestone = $milestone;
            }
        }

        return view('livewire.progress-card', [
            'totalFlashes' => $stats->total,
            'sailingCount' => $stats->sailing,
            'nonSailingCount' => min($stats->nonSailing, 5),
            'nextMilestone' => $nextMilestone,
            'earnedAwards' => $earnedAwards,
            'currentYear' => $this->currentYear,
        ]);
    }
}
