<?php

namespace App\Livewire;

use App\Models\Flash;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SailorLogs extends AdminComponent
{
    // Filters (selectedYear inherited from AdminComponent)
    #[Url]
    public ?int $selectedDistrict = null;

    #[Url]
    public ?int $selectedFleet = null;

    #[Url]
    public string $searchQuery = '';

    public function render()
    {
        $flashes = $this->getFilteredFlashes();

        return view('livewire.sailor-logs', [
            'flashes' => $flashes,
            'availableYears' => $this->getAvailableYears(),
            'availableDistricts' => $this->getAvailableDistricts(),
            'availableFleets' => $this->getAvailableFleets(),
            'totalCount' => $this->getTotalCount(),
        ]);
    }

    /**
     * Get filtered flashes with pagination.
     */
    private function getFilteredFlashes()
    {
        $query = Flash::query()
            ->with(['user', 'user.members' => function ($q) {
                $q->where('year', '<=', $this->selectedYear)
                    ->orderBy('year', 'desc');
            }, 'user.members.fleet', 'user.members.district'])
            ->whereYear('date', $this->selectedYear);

        // Apply search filter
        if ($this->searchQuery) {
            $search = strtolower($this->searchQuery);
            $query->whereHas('user', function ($q) use ($search) {
                $q->whereRaw('LOWER(first_name || " " || last_name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"]);
            });
        }

        // Apply district filter
        if ($this->selectedDistrict) {
            $query->whereHas('user.members', function ($q) {
                $q->where('district_id', $this->selectedDistrict)
                    ->where('year', '<=', $this->selectedYear);
            });
        }

        // Apply fleet filter
        if ($this->selectedFleet) {
            $query->whereHas('user.members', function ($q) {
                $q->where('fleet_id', $this->selectedFleet)
                    ->where('year', '<=', $this->selectedYear);
            });
        }

        return $query->orderBy('date', 'desc')->paginate(25);
    }

    /**
     * Get total count of filtered flashes (without pagination).
     */
    private function getTotalCount(): int
    {
        $query = Flash::query()
            ->whereYear('date', $this->selectedYear);

        if ($this->searchQuery) {
            $search = strtolower($this->searchQuery);
            $query->whereHas('user', function ($q) use ($search) {
                $q->whereRaw('LOWER(first_name || " " || last_name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($this->selectedDistrict) {
            $query->whereHas('user.members', function ($q) {
                $q->where('district_id', $this->selectedDistrict)
                    ->where('year', '<=', $this->selectedYear);
            });
        }

        if ($this->selectedFleet) {
            $query->whereHas('user.members', function ($q) {
                $q->where('fleet_id', $this->selectedFleet)
                    ->where('year', '<=', $this->selectedYear);
            });
        }

        return $query->count();
    }

    /**
     * Get all districts.
     */
    private function getAvailableDistricts(): Collection
    {
        return \DB::table('districts')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Get fleets filtered by selected district.
     */
    private function getAvailableFleets(): Collection
    {
        // If no district selected, return all fleets
        if (! $this->selectedDistrict) {
            return \DB::table('fleets')
                ->join('districts', 'fleets.district_id', '=', 'districts.id')
                ->select(
                    'fleets.id',
                    'fleets.fleet_number',
                    'fleets.fleet_name',
                    'districts.name as district_name'
                )
                ->orderBy('fleets.fleet_number')
                ->get();
        }

        // Return fleets for selected district
        return \DB::table('fleets')
            ->where('district_id', $this->selectedDistrict)
            ->orderBy('fleet_number')
            ->get(['id', 'fleet_number', 'fleet_name']);
    }

    /**
     * Export filtered flashes to CSV.
     */
    public function exportCsv(): StreamedResponse
    {
        // Build query with same filters as display
        $query = Flash::query()
            ->with(['user', 'user.members' => function ($q) {
                $q->where('year', '<=', $this->selectedYear)
                    ->orderBy('year', 'desc');
            }, 'user.members.fleet', 'user.members.district'])
            ->whereYear('date', $this->selectedYear);

        // Apply same filters
        if ($this->searchQuery) {
            $search = strtolower($this->searchQuery);
            $query->whereHas('user', function ($q) use ($search) {
                $q->whereRaw('LOWER(first_name || " " || last_name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($this->selectedDistrict) {
            $query->whereHas('user.members', function ($q) {
                $q->where('district_id', $this->selectedDistrict)
                    ->where('year', '<=', $this->selectedYear);
            });
        }

        if ($this->selectedFleet) {
            $query->whereHas('user.members', function ($q) {
                $q->where('fleet_id', $this->selectedFleet)
                    ->where('year', '<=', $this->selectedYear);
            });
        }

        $query->orderBy('date', 'desc');

        $filename = "sailor-logs-{$this->selectedYear}-".now()->format('Y-m-d-H-i-s').'.csv';

        $selectedYear = $this->selectedYear; // Capture for closure

        // Log export action
        \Log::channel('admin')->info('Sailor logs CSV export', [
            'admin_id' => auth()->id(),
            'admin_email' => auth()->user()->email,
            'action' => 'export_sailor_logs_csv',
            'year' => $this->selectedYear,
            'filters' => [
                'district' => $this->selectedDistrict,
                'fleet' => $this->selectedFleet,
                'search' => $this->searchQuery,
            ],
            'flash_count' => $query->count(),
        ]);

        return response()->streamDownload(function () use ($query, $selectedYear) {
            $handle = fopen('php://output', 'w');

            // Write UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header row
            fputcsv($handle, [
                'Date',
                'Sailor Name',
                'Email',
                'Activity Type',
                'Event Type',
                'Location',
                'Sail Number',
                'District',
                'Fleet',
                'Yacht Club',
                'Notes',
                'Created At',
            ], ',', '"', '');

            // Stream flashes in chunks
            $query->chunk(100, function ($flashes) use ($handle, $selectedYear) {
                foreach ($flashes as $flash) {
                    /** @var \App\Models\Flash $flash */
                    /** @var \App\Models\User $user */
                    $user = $flash->user;
                    $membership = $user->membershipForYear($selectedYear);

                    fputcsv($handle, [
                        $flash->date->format('Y-m-d'),
                        $user->name,
                        $user->email,
                        ucfirst($flash->activity_type),
                        $flash->event_type ? ucfirst(str_replace('_', ' ', $flash->event_type)) : '—',
                        $flash->location ?? '—',
                        $flash->sail_number ?? '—',
                        $membership?->district->name ?? '—',
                        $membership?->fleet->fleet_number ?? '—',
                        $user->yacht_club ?? '—',
                        $flash->notes ?? '',
                        $flash->created_at->format('Y-m-d H:i:s'),
                    ], ',', '"', '');
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-Download-Options' => 'noopen',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Update the year filter.
     */
    public function updatedSelectedYear(): void
    {
        $this->resetPage();
        $this->selectedDistrict = null;
        $this->selectedFleet = null;
    }

    /**
     * Update the district filter.
     */
    public function updatedSelectedDistrict(): void
    {
        $this->resetPage();
        $this->selectedFleet = null; // Reset fleet when district changes
    }

    /**
     * Update the fleet filter.
     */
    public function updatedSelectedFleet(): void
    {
        $this->resetPage();
    }

    /**
     * Update the search query.
     */
    public function updatedSearchQuery(): void
    {
        $this->resetPage();
    }

    /**
     * Clear all filters.
     */
    public function clearFilters(): void
    {
        $this->selectedDistrict = null;
        $this->selectedFleet = null;
        $this->searchQuery = '';
        $this->resetPage();

        // Dispatch event to clear TomSelect dropdowns
        $this->dispatch('filters-cleared');
    }
}
