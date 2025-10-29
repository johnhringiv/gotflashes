<?php

namespace App\Livewire;

use App\Models\AwardFulfillment;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAwardsDashboard extends Component
{
    use WithPagination;

    // Filters
    #[Url]
    public int $selectedYear;

    #[Url]
    public string $statusFilter = 'pending'; // all, pending, earned, processing, sent

    #[Url]
    public string $tierFilter = 'all'; // all, 10, 25, 50

    public string $searchQuery = '';

    // Selection
    public array $selectedAwards = [];

    // Confirmation modals
    public ?string $confirmingAction = null; // 'processing', 'sent', 'export', 'remove'

    public bool $showEarnedToSentWarning = false;

    public int $earnedToSentCount = 0;

    public bool $confirmEarnedToSent = false;

    // Downgrade warnings
    public bool $showDowngradeWarning = false;

    public int $downgradeCount = 0;

    public bool $confirmDowngrade = false;

    // Remove confirmation
    public int $removeCount = 0;

    public bool $confirmRemove = false;

    // Cache for unfiltered awards (only the expensive DB query)
    private ?Collection $cachedUnfilteredAwards = null;

    public function mount(): void
    {
        // Ensure user is admin
        if (! auth()->check() || ! auth()->user()->is_admin) {
            abort(403, 'Unauthorized. Admin access required.');
        }

        // Default to current year
        if (! isset($this->selectedYear)) {
            $this->selectedYear = now()->year;
        }
    }

    public function render()
    {
        $unfilteredAwards = $this->getAllUnfilteredAwards();
        $awards = $this->getFilteredAwards();

        return view('livewire.admin-awards-dashboard', [
            'awards' => $awards,
            'stats' => $this->getStats($unfilteredAwards), // Always show year totals
            'availableYears' => $this->getAvailableYears(),
        ]);
    }

    /**
     * Get count of selected awards (computed property for performance).
     */
    #[Computed]
    public function selectedCount(): int
    {
        return count($this->selectedAwards);
    }

    /**
     * Get all unfiltered awards (expensive DB query - cached per request).
     */
    private function getAllUnfilteredAwards(): Collection
    {
        // Return cached if available
        if ($this->cachedUnfilteredAwards !== null) {
            return $this->cachedUnfilteredAwards;
        }

        // Get all users with their flashes for the year
        $users = User::query()
            ->with(['flashes' => function ($q) {
                $q->whereYear('date', $this->selectedYear);
            }, 'awardFulfillments' => function ($q) {
                $q->where('year', $this->selectedYear);
            }, 'members' => function ($q) {
                $q->where('year', '<=', $this->selectedYear)
                    ->orderBy('year', 'desc');
            }])
            ->get();

        // Build award rows (one per user per tier)
        /** @phpstan-ignore argument.type */
        $awards = $users->flatMap(function (User $user) {
            $stats = $user->flashStatsForYear($this->selectedYear);
            $earnedTiers = collect([10, 25, 50])->filter(fn ($t) => $stats->total >= $t);
            /** @var Collection<int, AwardFulfillment> $fulfillments */
            $fulfillments = $user->awardFulfillments->keyBy('award_tier');

            // Precompute membership to avoid N+1 query in view
            $membership = $user->membershipForYear($this->selectedYear);

            // Create rows for earned tiers
            $rows = $earnedTiers->map(function (int $tier) use ($user, $stats, $fulfillments, $membership) {
                /** @var AwardFulfillment|null $fulfillment */
                $fulfillment = $fulfillments[$tier] ?? null;

                return [
                    'id' => "{$user->id}-{$tier}",
                    'user' => $user,
                    'membership' => $membership,
                    'tier' => $tier,
                    'total_days' => $stats->total,
                    'status' => $fulfillment ? $fulfillment->status : 'earned',
                    'fulfillment' => $fulfillment,
                    'threshold_date' => $user->thresholdDateForYear($this->selectedYear, $tier),
                    'discrepancy' => $fulfillment && $stats->total < $tier,
                ];
            });

            // Add rows for fulfillments where user no longer qualifies
            $lostTiers = $fulfillments->keys()->diff($earnedTiers);
            $lostRows = $lostTiers->map(function (int $tier) use ($user, $stats, $fulfillments, $membership) {
                /** @var AwardFulfillment $fulfillment */
                $fulfillment = $fulfillments[$tier];

                return [
                    'id' => "{$user->id}-{$tier}",
                    'user' => $user,
                    'membership' => $membership,
                    'tier' => $tier,
                    'total_days' => $stats->total,
                    'status' => $fulfillment->status,
                    'fulfillment' => $fulfillment,
                    'threshold_date' => null, // No longer reached
                    'discrepancy' => true,
                ];
            });

            /** @phpstan-ignore return.type */
            return $rows->concat($lostRows);
        });

        // Cache the unfiltered awards
        $this->cachedUnfilteredAwards = $awards;

        return $awards;
    }

    /**
     * Get filtered awards (applies filters to cached unfiltered data).
     */
    private function getFilteredAwards(): Collection
    {
        $awards = $this->getAllUnfilteredAwards();

        return $this->applyFilters($awards);
    }

    /**
     * Apply status, tier, and search filters to awards.
     */
    private function applyFilters(Collection $awards): Collection
    {
        // Status filter
        if ($this->statusFilter === 'pending') {
            // Pending = earned + processing (everything except sent)
            $awards = $awards->filter(fn ($award) => $award['status'] !== 'sent');
        } elseif ($this->statusFilter !== 'all') {
            $awards = $awards->filter(fn ($award) => $award['status'] === $this->statusFilter);
        }

        // Tier filter
        if ($this->tierFilter !== 'all') {
            $awards = $awards->filter(fn ($award) => $award['tier'] == $this->tierFilter);
        }

        // Search query
        if ($this->searchQuery) {
            $query = strtolower($this->searchQuery);
            $awards = $awards->filter(function ($award) use ($query) {
                return str_contains(strtolower($award['user']->name), $query)
                    || str_contains(strtolower($award['user']->email), $query);
            });
        }

        return $awards;
    }

    /**
     * Get statistics for the current year and filters.
     */
    private function getStats(Collection $awards): array
    {
        return [
            'earned' => $awards->where('status', 'earned')->count(),
            'processing' => $awards->where('status', 'processing')->count(),
            'sent' => $awards->where('status', 'sent')->count(),
        ];
    }

    /**
     * Get all years that have flash activity.
     */
    private function getAvailableYears(): array
    {
        return \DB::table('flashes')
            ->selectRaw('DISTINCT strftime("%Y", date) as year')
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->toArray();
    }

    /**
     * Open confirmation modal for marking as processing.
     */
    public function confirmMarkAsProcessing(): void
    {
        $this->validate([
            'selectedAwards' => 'required|array|min:1',
        ]);

        // Count how many are currently sent (downgrades)
        $downgrades = 0;
        foreach ($this->selectedAwards as $awardId) {
            [$userId, $tier] = explode('-', $awardId);
            $fulfillment = AwardFulfillment::where([
                'user_id' => $userId,
                'year' => $this->selectedYear,
                'award_tier' => $tier,
            ])->first();

            if ($fulfillment && $fulfillment->status === 'sent') {
                $downgrades++;
            }
        }

        $this->showDowngradeWarning = $downgrades > 0;
        $this->downgradeCount = $downgrades;
        $this->confirmingAction = 'processing';
    }

    /**
     * Bulk mark selected awards as "processing".
     */
    public function bulkMarkAsProcessing(): void
    {
        $this->validate([
            'selectedAwards' => 'required|array|min:1',
        ]);

        $updated = 0;
        $unchanged = 0;
        $affectedAwards = [];

        foreach ($this->selectedAwards as $awardId) {
            [$userId, $tier] = explode('-', $awardId);

            $user = User::find($userId);
            if (! $user) {
                continue;
            }

            // Check if already in database
            $fulfillment = AwardFulfillment::where([
                'user_id' => $userId,
                'year' => $this->selectedYear,
                'award_tier' => $tier,
            ])->first();

            if ($fulfillment) {
                // Already exists - allow status change even with discrepancy
                if ($fulfillment->status === 'processing') {
                    $unchanged++; // Already processing, no-op
                } else {
                    // Sent → Processing (downgrade)
                    $oldStatus = $fulfillment->status;
                    $fulfillment->update(['status' => 'processing']);
                    $updated++;
                    $affectedAwards[] = [
                        'user_id' => $userId,
                        'year' => $this->selectedYear,
                        'tier' => $tier,
                        'transition' => "{$oldStatus} → processing",
                    ];
                }
            } else {
                // Creating new fulfillment - verify user qualifies
                $stats = $user->flashStatsForYear($this->selectedYear);
                if ($stats->total < $tier) {
                    continue; // User doesn't qualify for new award
                }

                // Create new (Earned → Processing)
                AwardFulfillment::create([
                    'user_id' => $userId,
                    'year' => $this->selectedYear,
                    'award_tier' => $tier,
                    'status' => 'processing',
                ]);
                $updated++;
                $affectedAwards[] = [
                    'user_id' => $userId,
                    'year' => $this->selectedYear,
                    'tier' => $tier,
                    'transition' => 'earned → processing',
                ];
            }
        }

        // Log admin action
        if ($updated > 0 || $unchanged > 0) {
            \Log::channel('admin')->info('Bulk mark as processing', [
                'admin_id' => auth()->id(),
                'admin_email' => auth()->user()->email,
                'action' => 'bulk_mark_as_processing',
                'year' => $this->selectedYear,
                'affected_awards' => $affectedAwards,
                'updated_count' => $updated,
                'unchanged_count' => $unchanged,
            ]);
        }

        $this->confirmingAction = null;
        $this->showDowngradeWarning = false;
        $this->confirmDowngrade = false;
        $this->selectedAwards = [];
        $this->cachedUnfilteredAwards = null; // Clear cache - data changed

        $this->dispatch('toast', [
            'message' => $this->formatToastMessage($updated, $unchanged, 'processing'),
            'type' => $updated > 0 ? 'success' : 'info',
        ]);
    }

    /**
     * Open confirmation modal for marking as sent.
     * Shows warning if any earned awards are selected.
     */
    public function confirmMarkAsSent(): void
    {
        $this->validate([
            'selectedAwards' => 'required|array|min:1',
        ]);

        // Count how many selected awards are "earned" (not in database yet)
        $earnedCount = 0;
        foreach ($this->selectedAwards as $awardId) {
            [$userId, $tier] = explode('-', $awardId);

            $existing = AwardFulfillment::where([
                'user_id' => $userId,
                'year' => $this->selectedYear,
                'award_tier' => $tier,
            ])->first();

            if (! $existing) {
                $earnedCount++;
            }
        }

        $this->showEarnedToSentWarning = $earnedCount > 0;
        $this->earnedToSentCount = $earnedCount;
        $this->confirmingAction = 'sent';
    }

    /**
     * Bulk mark selected awards as "sent".
     * Handles both processing and earned awards.
     */
    public function bulkMarkAsSent(): void
    {
        $this->validate([
            'selectedAwards' => 'required|array|min:1',
        ]);

        $updated = 0;
        $unchanged = 0;
        $affectedAwards = [];

        foreach ($this->selectedAwards as $awardId) {
            [$userId, $tier] = explode('-', $awardId);

            $user = User::find($userId);
            if (! $user) {
                continue;
            }

            // Try to find existing fulfillment
            $fulfillment = AwardFulfillment::where([
                'user_id' => $userId,
                'year' => $this->selectedYear,
                'award_tier' => $tier,
            ])->first();

            if ($fulfillment) {
                // Already exists - allow status change even with discrepancy
                if ($fulfillment->status === 'sent') {
                    $unchanged++; // Already sent, no-op
                } else {
                    // Processing → Sent (upgrade)
                    $oldStatus = $fulfillment->status;
                    $fulfillment->update(['status' => 'sent']);
                    $updated++;
                    $affectedAwards[] = [
                        'user_id' => $userId,
                        'year' => $this->selectedYear,
                        'tier' => $tier,
                        'transition' => "{$oldStatus} → sent",
                    ];
                }
            } else {
                // Creating new fulfillment - verify user qualifies
                $stats = $user->flashStatsForYear($this->selectedYear);
                if ($stats->total < $tier) {
                    continue; // User doesn't qualify for new award
                }

                // Create new (Earned → Sent, skip processing)
                AwardFulfillment::create([
                    'user_id' => $userId,
                    'year' => $this->selectedYear,
                    'award_tier' => $tier,
                    'status' => 'sent',
                ]);
                $updated++;
                $affectedAwards[] = [
                    'user_id' => $userId,
                    'year' => $this->selectedYear,
                    'tier' => $tier,
                    'transition' => 'earned → sent',
                ];
            }
        }

        // Log admin action
        if ($updated > 0 || $unchanged > 0) {
            \Log::channel('admin')->info('Bulk mark as sent', [
                'admin_id' => auth()->id(),
                'admin_email' => auth()->user()->email,
                'action' => 'bulk_mark_as_sent',
                'year' => $this->selectedYear,
                'affected_awards' => $affectedAwards,
                'updated_count' => $updated,
                'unchanged_count' => $unchanged,
            ]);
        }

        $this->confirmingAction = null;
        $this->showEarnedToSentWarning = false;
        $this->confirmEarnedToSent = false;
        $this->selectedAwards = [];
        $this->cachedUnfilteredAwards = null; // Clear cache - data changed

        $this->dispatch('toast', [
            'message' => $this->formatToastMessage($updated, $unchanged, 'sent'),
            'type' => $updated > 0 ? 'success' : 'info',
        ]);
    }

    /**
     * Open confirmation modal for CSV export.
     */
    public function confirmExportToCsv(): void
    {
        $this->validate([
            'selectedAwards' => 'required|array|min:1',
        ]);

        $this->confirmingAction = 'export';
    }

    /**
     * Export selected awards to CSV with true streaming for memory efficiency.
     */
    public function exportToCsv(): StreamedResponse
    {
        $this->validate([
            'selectedAwards' => 'required|array|min:1',
        ]);

        // Parse selected award IDs to get user IDs and tiers
        $selectedData = collect($this->selectedAwards)->map(function ($awardId) {
            [$userId, $tier] = explode('-', $awardId);

            return ['user_id' => (int) $userId, 'tier' => (int) $tier];
        })->groupBy('user_id');

        // Include timestamp for multiple exports per day
        $filename = "awards-export-{$this->selectedYear}-".now()->format('Y-m-d-H-i-s').'.csv';

        $this->confirmingAction = null;

        $selectedYear = $this->selectedYear; // Capture for closure

        return response()->streamDownload(function () use ($selectedData, $selectedYear) {
            $handle = fopen('php://output', 'w');

            // Write UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header row
            fputcsv($handle, [
                'Name',
                'Fleet',
                'District',
                'Email',
                'Address Line 1',
                'Address Line 2',
                'City',
                'State',
                'ZIP',
                'Country',
                'Award Tier',
                'Total Days',
                'Date Threshold Reached',
                'Status',
            ]);

            // Process users in chunks (true streaming)
            User::query()
                ->whereIn('id', $selectedData->keys())
                ->with([
                    'awardFulfillments' => function ($q) use ($selectedYear) {
                        $q->where('year', $selectedYear);
                    },
                    'members' => function ($q) use ($selectedYear) {
                        $q->where('year', '<=', $selectedYear)
                            ->orderBy('year', 'desc');
                    },
                    'members.fleet',
                    'members.district',
                    'flashes' => function ($q) use ($selectedYear) {
                        $q->whereYear('date', $selectedYear);
                    },
                ])
                ->chunk(50, function ($users) use ($handle, $selectedData, $selectedYear) {
                    foreach ($users as $user) {
                        $membership = $user->membershipForYear($selectedYear);
                        $stats = $user->flashStatsForYear($selectedYear);
                        $tiers = $selectedData[$user->id]->pluck('tier');

                        foreach ($tiers as $tier) {
                            /** @var AwardFulfillment|null $fulfillment */
                            $fulfillment = $user->awardFulfillments
                                ->where('award_tier', $tier)
                                ->first();

                            fputcsv($handle, [
                                $user->name,
                                $membership?->fleet->fleet_number ?? '—',
                                $membership?->district->name ?? '—',
                                $user->email,
                                $user->address_line1 ?? '',
                                $user->address_line2 ?? '',
                                $user->city ?? '',
                                $user->state ?? '',
                                $user->zip_code ?? '',
                                $user->country ?? '',
                                $tier,
                                $stats->total,
                                $user->thresholdDateForYear($selectedYear, $tier)?->format('Y-m-d') ?? 'N/A',
                                $fulfillment ? $fulfillment->status : 'earned',
                            ]);
                        }
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
     * Open confirmation modal for resetting awards to earned status.
     */
    public function confirmRemoveFromDatabase(): void
    {
        $this->validate([
            'selectedAwards' => 'required|array|min:1',
        ]);

        // Count how many are actually in database
        $inDatabase = 0;
        foreach ($this->selectedAwards as $awardId) {
            [$userId, $tier] = explode('-', $awardId);
            if (AwardFulfillment::where([
                'user_id' => $userId,
                'year' => $this->selectedYear,
                'award_tier' => $tier,
            ])->exists()) {
                $inDatabase++;
            }
        }

        if ($inDatabase === 0) {
            $this->dispatch('toast', [
                'message' => 'No awards to reset (all are already Earned)',
                'type' => 'info',
            ]);

            return;
        }

        $this->confirmingAction = 'remove';
        $this->removeCount = $inDatabase;
    }

    /**
     * Reset selected awards to earned status (removes fulfillment records).
     */
    public function bulkRemoveFromDatabase(): void
    {
        $this->validate([
            'selectedAwards' => 'required|array|min:1',
        ]);

        $removed = 0;
        $affectedAwards = [];

        foreach ($this->selectedAwards as $awardId) {
            [$userId, $tier] = explode('-', $awardId);

            $fulfillment = AwardFulfillment::where([
                'user_id' => $userId,
                'year' => $this->selectedYear,
                'award_tier' => $tier,
            ])->first();

            if ($fulfillment) {
                $affectedAwards[] = [
                    'user_id' => $userId,
                    'year' => $this->selectedYear,
                    'tier' => $tier,
                    'transition' => "{$fulfillment->status} → earned",
                ];
                $fulfillment->delete();
                $removed++;
            }
        }

        // Log admin action
        if ($removed > 0) {
            \Log::channel('admin')->info('Bulk reset to earned', [
                'admin_id' => auth()->id(),
                'admin_email' => auth()->user()->email,
                'action' => 'bulk_reset_to_earned',
                'year' => $this->selectedYear,
                'affected_awards' => $affectedAwards,
                'reset_count' => $removed,
            ]);
        }

        $this->confirmingAction = null;
        $this->confirmRemove = false;
        $this->selectedAwards = [];
        $this->cachedUnfilteredAwards = null; // Clear cache - data changed

        $this->dispatch('toast', [
            'message' => "{$removed} award(s) reset to Earned status",
            'type' => 'success',
        ]);
    }

    /**
     * Cancel the current confirmation action.
     */
    public function cancelConfirmation(): void
    {
        $this->confirmingAction = null;
        $this->showEarnedToSentWarning = false;
        $this->confirmEarnedToSent = false;
        $this->showDowngradeWarning = false;
        $this->confirmDowngrade = false;
        $this->confirmRemove = false;
    }

    /**
     * Select all awards on the current page with a specific status.
     */
    public function selectAllByStatus(string $status): void
    {
        $awards = $this->getFilteredAwards();

        if ($status === 'pending') {
            // Select earned + processing (everything except sent)
            $awards = $awards->filter(fn ($award) => $award['status'] !== 'sent');
        } elseif ($status !== 'all') {
            $awards = $awards->filter(fn ($award) => $award['status'] === $status);
        }

        $this->selectedAwards = $awards->pluck('id')->toArray();
    }

    /**
     * Clear all selections.
     */
    public function clearSelection(): void
    {
        $this->selectedAwards = [];
    }

    /**
     * Update the year filter.
     */
    public function updatedSelectedYear(): void
    {
        $this->cachedUnfilteredAwards = null; // Clear cache - year change requires new query
        $this->resetPage();
        $this->clearSelection();
    }

    /**
     * Update the status filter.
     */
    public function updatedStatusFilter(): void
    {
        $this->resetPage();
        $this->clearSelection();
    }

    /**
     * Update the tier filter.
     */
    public function updatedTierFilter(): void
    {
        $this->resetPage();
        $this->clearSelection();
    }

    /**
     * Update the search query.
     */
    public function updatedSearchQuery(): void
    {
        $this->resetPage();
        $this->clearSelection(); // Clear selection when search changes
    }

    /**
     * Format toast message with split counts.
     */
    private function formatToastMessage(int $updated, int $unchanged, string $status): string
    {
        if ($updated === 0 && $unchanged === 0) {
            return 'No awards to update';
        }

        if ($updated === 0) {
            return "{$unchanged} already {$status}";
        }

        if ($unchanged === 0) {
            return "{$updated} awards marked as {$status}";
        }

        // Both have values
        return "{$updated} updated, {$unchanged} already {$status}";
    }
}
