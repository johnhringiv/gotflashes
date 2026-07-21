<?php

namespace App\Livewire;

use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Base component for admin-only Livewire components.
 * Provides shared functionality for authorization, year selection, and common queries.
 */
abstract class AdminComponent extends Component
{
    use WithPagination;

    #[Url]
    public int $selectedYear;

    public function mount(): void
    {
        $this->authorizeAccess();
        $this->initializeYear();
    }

    /**
     * Access gate for the component. Defaults to award-admin; subclasses that
     * need the elevated site-operator tier override this (see AdminSettings).
     */
    protected function authorizeAccess(): void
    {
        $this->authorizeAdmin();
    }

    /**
     * Ensure the current user is an admin.
     * Aborts with 403 if not authenticated or not an admin.
     */
    protected function authorizeAdmin(): void
    {
        if (! auth()->check() || ! auth()->user()->is_admin) {
            abort(403, 'Unauthorized. Admin access required.');
        }
    }

    /**
     * Ensure the current user holds the elevated site-operator tier.
     * Aborts with 403 if not authenticated or not a super admin.
     */
    protected function authorizeSuperAdmin(): void
    {
        if (! auth()->check() || ! auth()->user()->isSuperAdmin()) {
            abort(403, 'Unauthorized. Site admin access required.');
        }
    }

    /**
     * Initialize the selected year to the current year if not set.
     */
    protected function initializeYear(): void
    {
        if (! isset($this->selectedYear)) {
            $this->selectedYear = now()->year;
        }
    }

    /**
     * Get all years that have flash activity.
     * Returns years in descending order (most recent first).
     *
     * @return array<int>
     */
    protected function getAvailableYears(): array
    {
        return \DB::table('flashes')
            ->selectRaw('DISTINCT strftime("%Y", date) as year')
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->toArray();
    }
}
