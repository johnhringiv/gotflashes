<?php

namespace App\Livewire;

use App\Models\Flash;
use App\Services\DateRangeService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class FlashForm extends Component
{
    public ?Flash $flash = null;

    public string $mode = 'create'; // 'create' or 'edit'

    public string $submitText = 'Log Activity';

    // Form data - will be wire:model bound
    public array $dates = [];

    public string $date = '';

    public string $activity_type = '';

    public string $event_type = '';

    public string $location = '';

    public string $sail_number = '';

    public string $notes = '';

    public function mount(?Flash $flash = null, string $submitText = 'Log Activity')
    {
        $this->flash = $flash;
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

    public function save()
    {
        // Handle edit mode vs create mode differently
        if ($this->mode === 'edit') {
            return $this->update();
        }

        // Get allowed date range based on grace period
        [$minDate, $maxDate] = DateRangeService::getAllowedDateRange();

        $this->validate([
            'dates' => 'required|array|min:1',
            'dates.*' => [
                'required',
                'date',
                'after_or_equal:'.$minDate->format('Y-m-d'),
                'before_or_equal:'.$maxDate,
            ],
            'activity_type' => 'required|in:sailing,maintenance,race_committee',
            'event_type' => [
                'required_if:activity_type,sailing',
                'nullable',
                'in:regatta,club_race,practice,leisure',
            ],
            'location' => 'nullable|string|max:255',
            'sail_number' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        $dates = $this->dates;

        // Check for duplicate dates before creating any (database-level filtering)
        $existingDates = auth()->user()->flashes()
            ->whereIn(DB::raw('DATE(date)'), $dates)
            ->pluck('date')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->toArray();

        if (! empty($existingDates)) {
            $this->addError('dates', 'You already have activities logged for: '.implode(', ', $existingDates).'. Please remove these dates or edit existing entries.');

            return;
        }

        // Use transaction to ensure all-or-nothing
        DB::transaction(function () use ($dates) {
            foreach ($dates as $date) {
                auth()->user()->flashes()->create(array_merge($this->getFlashData(), ['date' => $date]));
            }
        });

        // Check if this is a non-sailing activity and if they've reached the limit
        $hasWarning = false;
        if (in_array($this->activity_type, ['maintenance', 'race_committee'])) {
            $currentYear = now()->year;
            $stats = auth()->user()->flashStatsForYear($currentYear);

            if ($stats->nonSailing > 5) {
                $hasWarning = true;
            }
        }

        $count = count($dates);
        $message = $count === 1 ? 'Flash logged successfully!' : "{$count} flashes logged successfully!";

        if ($hasWarning) {
            $this->dispatch('toast', [
                'type' => 'warning',
                'message' => "Non-sailing days logged! Heads up: You've already got 5 non-sailing days counting toward awards. Keep logging though—we want to see all your Lightning time!",
            ]);
        } else {
            $this->dispatch('toast', [
                'type' => 'success',
                'message' => $message,
            ]);
        }

        // Emit event to refresh other components
        $this->dispatch('flash-saved');

        // Reset form
        $this->reset(['dates', 'activity_type', 'event_type', 'location', 'sail_number', 'notes']);
    }

    public function update()
    {
        // Authorization check using Laravel's authorize method
        if (! $this->flash) {
            abort(403);
        }

        $this->authorize('update', $this->flash);

        // Get allowed date range based on grace period
        [$minDate, $maxDate] = DateRangeService::getAllowedDateRange();

        // Check if flash is within editable date range
        if (! $this->flash->isEditable($minDate, $maxDate)) {
            abort(403, 'This activity is outside the editable date range.');
        }

        $this->validate([
            'date' => [
                'required',
                'date',
                'after_or_equal:'.$minDate->format('Y-m-d'),
                'before_or_equal:'.$maxDate->format('Y-m-d'),
            ],
            'activity_type' => 'required|in:sailing,maintenance,race_committee',
            'event_type' => [
                'required_if:activity_type,sailing',
                'nullable',
                'in:regatta,club_race,practice,leisure',
            ],
            'location' => 'nullable|string|max:255',
            'sail_number' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        // Check for duplicate date (excluding current flash)
        $exists = auth()->user()->flashes()
            ->whereDate('date', $this->date)
            ->where('id', '!=', $this->flash->id)
            ->exists();

        if ($exists) {
            $this->addError('date', 'You already have an activity logged for this date. Please choose a different date.');

            return;
        }

        // Update the flash
        $this->flash->update(array_merge($this->getFlashData(), [
            'date' => $this->date,
        ]));

        $this->dispatch('toast', [
            'type' => 'success',
            'message' => 'Flash updated successfully!',
        ]);

        // Emit event to refresh other components
        $this->dispatch('flash-saved');

        // Close the edit modal
        $this->dispatch('close-edit-modal');
    }

    #[On('flash-saved')]
    public function refreshExistingDates()
    {
        // This method triggers a re-render, which will fetch fresh existingDates
        // This ensures the calendar updates after a flash is saved
    }

    #[On('flash-deleted')]
    public function refreshAfterDelete()
    {
        // This method triggers a re-render after a flash is deleted
        // This ensures the calendar updates when dates are removed
    }

    /**
     * Get flash data array for create/update operations.
     *
     * @return array<string, mixed>
     */
    private function getFlashData(): array
    {
        return [
            'activity_type' => $this->activity_type,
            'event_type' => $this->event_type ?: null,
            'location' => $this->location ?: null,
            'sail_number' => $this->sail_number ?: null,
            'notes' => $this->notes ?: null,
        ];
    }

    public function render()
    {
        // These are calculated fresh on every render - always current!
        [$minDate, $maxDate] = DateRangeService::getAllowedDateRange();

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
}
