<?php

namespace App\Livewire;

use App\Models\Flash;
use Carbon\Carbon;
use Livewire\Component;

class FlashForm extends Component
{
    public ?Flash $flash = null;

    public string $mode = 'create'; // 'create' or 'edit'

    public string $action = '';

    public string $method = 'POST';

    public string $submitText = 'Log Activity';

    // Form data - will be wire:model bound
    public $dates = [];

    public $date = '';

    public $activity_type = '';

    public $event_type = '';

    public $location = '';

    public $sail_number = '';

    public $notes = '';

    public function mount(?Flash $flash = null, string $action = '', string $method = 'POST', string $submitText = 'Log Activity')
    {
        $this->flash = $flash;
        $this->action = $action;
        $this->method = $method;
        $this->submitText = $submitText;

        // Determine mode based on whether we have a flash with data
        $this->mode = ($flash && $flash->exists) ? 'edit' : 'create';

        // Pre-fill form if editing
        if ($this->mode === 'edit') {
            $this->date = $this->flash->date->format('Y-m-d');
            $this->activity_type = $this->flash->activity_type;
            $this->event_type = $this->flash->event_type ?? '';
            $this->location = $this->flash->location ?? '';
            $this->sail_number = $this->flash->sail_number ?? '';
            $this->notes = $this->flash->notes ?? '';
        }
    }

    public function render()
    {
        // These are calculated fresh on every render - always current!
        $now = now();
        $minDate = $this->getMinAllowedDate($now);
        $maxDate = $now->copy()->addDay();

        // Get existing dates for the user within selectable range
        $user = auth()->user();
        $existingDates = $user->flashes()
            ->where('date', '>=', $minDate)
            ->where('date', '<=', $maxDate)
            ->pluck('date')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->toArray();

        return view('livewire.flash-form', [
            'minDate' => $minDate,
            'maxDate' => $maxDate,
            'existingDates' => $existingDates,
        ]);
    }

    /**
     * Calculate the minimum allowed date based on grace period logic.
     * January allows previous year entries, February onward restricts to current year.
     */
    private function getMinAllowedDate(Carbon $now): Carbon
    {
        $minDate = $now->copy()->startOfYear();
        if ($now->month === 1) {
            // January: allow previous year entries (grace period)
            $minDate = $now->copy()->subYear()->startOfYear();
        }

        return $minDate;
    }
}
