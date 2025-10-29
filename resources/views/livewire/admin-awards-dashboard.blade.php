<div>
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Award Fulfillment Dashboard</h1>
    </div>

    <!-- Filters -->
    <div class="card bg-base-100 shadow mb-6">
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Year Filter -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Year</span>
                    </label>
                    <select wire:model.live="selectedYear" class="select select-bordered">
                        @foreach($availableYears as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Tier Filter -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Award Tier</span>
                    </label>
                    <select wire:model.live="tierFilter" class="select select-bordered">
                        <option value="all">All Tiers</option>
                        <option value="10">10 Days</option>
                        <option value="25">25 Days</option>
                        <option value="50">50 Days</option>
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Status</span>
                    </label>
                    <select wire:model.live="statusFilter" class="select select-bordered">
                        <option value="pending">Pending (Earned + Processing)</option>
                        <option value="all">All Statuses</option>
                        <option value="earned">Earned</option>
                        <option value="processing">Processing</option>
                        <option value="sent">Sent</option>
                    </select>
                </div>

                <!-- Search -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Search</span>
                    </label>
                    <input type="text"
                           wire:model.live.debounce.300ms="searchQuery"
                           placeholder="Name or email..."
                           class="input input-bordered">
                </div>
            </div>
        </div>
    </div>

    <!-- Stats (Year Totals - Not Affected by Filters) -->
    <div class="mb-2 text-sm text-base-content/70 font-semibold">
        {{ $selectedYear }} Totals
    </div>
    <div class="stats stats-vertical lg:stats-horizontal shadow-lg mb-6 w-full">
        <div class="stat">
            <div class="stat-title">Pending</div>
            <div class="stat-value text-primary">{{ $stats['earned'] + $stats['processing'] }}</div>
            <div class="stat-desc">Needs action</div>
        </div>
        <div class="stat">
            <div class="stat-title">Earned</div>
            <div class="stat-value text-warning">{{ $stats['earned'] }}</div>
            <div class="stat-desc">Not yet processed</div>
        </div>
        <div class="stat">
            <div class="stat-title">Processing</div>
            <div class="stat-value text-info">{{ $stats['processing'] }}</div>
            <div class="stat-desc">Being prepared</div>
        </div>
        <div class="stat">
            <div class="stat-title">Sent</div>
            <div class="stat-value text-success">{{ $stats['sent'] }}</div>
            <div class="stat-desc">Completed</div>
        </div>
    </div>

    <!-- Awards Table -->
    @if($awards->count() > 0)
        <div class="card bg-base-100 shadow overflow-x-auto mb-20">
            <table class="table">
                <thead class="bg-base-300 border-b-2 border-base-content/20">
                    <tr>
                        <th class="w-24">
                            @if($this->selectedCount === $awards->count() && $awards->count() > 0)
                                <button wire:click="clearSelection"
                                        class="btn btn-sm btn-error">
                                    Clear
                                </button>
                            @else
                                <button wire:click="selectAllByStatus('all')"
                                        class="btn btn-sm btn-primary">
                                    Select All
                                </button>
                            @endif
                        </th>
                        <th class="font-bold">Name</th>
                        <th class="font-bold">Address</th>
                        <th class="font-bold">Email</th>
                        <th class="font-bold text-center">Fleet</th>
                        <th class="font-bold text-center">District</th>
                        <th class="font-bold text-center">Tier</th>
                        <th class="font-bold text-center">Total Days</th>
                        <th class="font-bold text-center">Status</th>
                        <th class="font-bold text-center">Date Reached</th>
                        <th class="font-bold text-center">Status Updated</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($awards as $award)
                        @php
                            $user = $award['user'];
                            $membership = $award['membership']; // Precomputed in component
                        @endphp
                        <tr class="hover" wire:key="award-{{ $award['id'] }}">
                            <td>
                                <input type="checkbox"
                                       class="checkbox checkbox-primary checkbox-sm"
                                       wire:model.live="selectedAwards"
                                       value="{{ $award['id'] }}">
                            </td>
                            <td class="font-medium">
                                {{ $user->name }}
                                @if($award['discrepancy'])
                                    <div class="tooltip" data-tip="User currently has {{ $award['total_days'] }} days but was processed for {{ $award['tier'] }}-day award">
                                        <span class="badge badge-warning badge-sm ml-2">⚠</span>
                                    </div>
                                @endif
                            </td>
                            <td class="text-sm">
                                @if($user->address_line1)
                                    {{ $user->address_line1 }}@if($user->address_line2), {{ $user->address_line2 }}@endif
                                    <br>
                                    {{ $user->city }}, {{ $user->state }} {{ $user->zip_code }}
                                @else
                                    <span class="text-base-content/50">No address</span>
                                @endif
                            </td>
                            <td class="text-sm">{{ $user->email }}</td>
                            <td class="text-center">{{ $membership?->fleet?->fleet_number ?? '—' }}</td>
                            <td class="text-center">{{ $membership?->district?->name ?? '—' }}</td>
                            <td class="text-center">
                                <span class="badge badge-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    {{ $award['tier'] }}
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-accent">{{ $award['total_days'] }}</span>
                            </td>
                            <td class="text-center">
                                @if($award['status'] === 'earned')
                                    <span class="badge badge-warning">Earned</span>
                                @elseif($award['status'] === 'processing')
                                    <span class="badge badge-info">Processing</span>
                                @elseif($award['status'] === 'sent')
                                    <span class="badge badge-success">Sent</span>
                                @endif
                            </td>
                            <td class="text-center text-sm">
                                {{ $award['threshold_date'] ? $award['threshold_date']->format('M j, Y') : '—' }}
                            </td>
                            <td class="text-center text-sm">
                                @if($award['fulfillment'])
                                    {{ $award['fulfillment']->updated_at->format('M j, Y') }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="alert alert-info">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>No awards found matching the current filters.</span>
        </div>
    @endif

    <!-- Bulk Action Bar (Sticky) -->
    @if($this->selectedCount > 0)
        <div class="fixed bottom-0 left-0 right-0 bg-base-200 border-t-2 border-base-300 shadow-xl z-50">
            <div class="container mx-auto px-4 py-4">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold">{{ $this->selectedCount }} awards selected</span>
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="confirmRemoveFromDatabase" class="btn btn-warning">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Reset to Earned
                        </button>
                        <button wire:click="confirmMarkAsProcessing" class="btn btn-info">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            Mark as Processing
                        </button>
                        <button wire:click="confirmMarkAsSent" class="btn btn-success">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            Mark as Sent
                        </button>
                        <button wire:click="confirmExportToCsv" class="btn btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Export CSV
                        </button>
                        <button wire:click="clearSelection" class="btn btn-ghost btn-outline">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Mark as Processing Confirmation Modal -->
    @if($confirmingAction === 'processing')
        <div class="modal modal-open" role="dialog">
            <div class="modal-box">
                <button wire:click="cancelConfirmation" class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
                <h3 class="font-bold text-lg mb-4">Mark as Processing</h3>

                @if($showDowngradeWarning)
                    <div class="alert alert-warning mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <h3 class="font-bold">Warning: Downgrading Status</h3>
                            <div class="text-sm">{{ $downgradeCount }} award(s) are currently "sent" and will be reverted back to processing.</div>
                        </div>
                    </div>

                    <div class="form-control mb-4">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" wire:model.live="confirmDowngrade" class="checkbox checkbox-warning" />
                            <span class="label-text">
                                I understand this will revert {{ $downgradeCount }} sent award(s) back to processing
                            </span>
                        </label>
                    </div>
                @endif

                <p class="py-4">Are you sure you want to mark <strong>{{ $this->selectedCount }}</strong> award(s) as processing?</p>
                <p class="text-sm text-base-content/70">This will create database records indicating these awards are being prepared for mailing.</p>

                <div class="modal-action">
                    <button wire:click="cancelConfirmation" class="btn">Cancel</button>
                    <button wire:click="bulkMarkAsProcessing"
                            class="btn btn-info"
                            @disabled($showDowngradeWarning && !$confirmDowngrade)>
                        Mark as Processing
                    </button>
                </div>
            </div>
            <div class="modal-backdrop" wire:click="cancelConfirmation"></div>
        </div>
    @endif

    <!-- Mark as Sent Confirmation Modal -->
    @if($confirmingAction === 'sent')
        <div class="modal modal-open" role="dialog">
            <div class="modal-box">
                <button wire:click="cancelConfirmation" class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
                <h3 class="font-bold text-lg mb-4">Mark as Sent</h3>

                @if($showEarnedToSentWarning)
                    <div class="alert alert-warning mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <h3 class="font-bold">Warning: Skipping Processing</h3>
                            <div class="text-sm">{{ $earnedToSentCount }} award(s) are "earned" and will be marked as sent directly.</div>
                        </div>
                    </div>

                    <div class="form-control mb-4">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" wire:model.live="confirmEarnedToSent" class="checkbox checkbox-warning" />
                            <span class="label-text">
                                I understand {{ $earnedToSentCount }} award(s) are currently Earned
                            </span>
                        </label>
                    </div>
                @endif

                <p class="py-4">Are you sure you want to mark <strong>{{ $this->selectedCount }}</strong> award(s) as sent?</p>
                <p class="text-sm text-base-content/70">This will update the status indicating these awards have been mailed out.</p>

                <div class="modal-action">
                    <button wire:click="cancelConfirmation" class="btn">Cancel</button>
                    <button wire:click="bulkMarkAsSent"
                            class="btn btn-success"
                            @disabled($showEarnedToSentWarning && !$confirmEarnedToSent)>
                        Mark as Sent
                    </button>
                </div>
            </div>
            <div class="modal-backdrop" wire:click="cancelConfirmation"></div>
        </div>
    @endif

    <!-- Export CSV Confirmation Modal -->
    @if($confirmingAction === 'export')
        <div class="modal modal-open" role="dialog">
            <div class="modal-box">
                <button wire:click="cancelConfirmation" class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
                <h3 class="font-bold text-lg mb-4">Export to CSV</h3>
                <p class="py-4">Export <strong>{{ $this->selectedCount }}</strong> award(s) to CSV?</p>
                <p class="text-sm text-base-content/70">The CSV file will include names, addresses, award tiers, and status information for mailing labels.</p>
                <p class="text-sm text-base-content/70 mt-2">Filename will include a timestamp: <code class="text-xs">awards-export-{{ $selectedYear }}-{{ now()->format('Y-m-d-H-i-s') }}.csv</code></p>

                <div class="modal-action">
                    <button wire:click="cancelConfirmation" class="btn">Cancel</button>
                    <button wire:click="exportToCsv" class="btn btn-primary">Export CSV</button>
                </div>
            </div>
            <div class="modal-backdrop" wire:click="cancelConfirmation"></div>
        </div>
    @endif

    <!-- Reset to Earned Confirmation Modal -->
    @if($confirmingAction === 'remove')
        <div class="modal modal-open" role="dialog">
            <div class="modal-box">
                <button wire:click="cancelConfirmation" class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
                <h3 class="font-bold text-lg mb-4">Reset to Earned Status</h3>

                <div class="alert alert-warning mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div>
                        <h3 class="font-bold">Reset Award Status</h3>
                        <div class="text-sm">
                            This will reset {{ $removeCount }} award(s) back to "Earned" status.
                            Processing/Sent status will be cleared.
                        </div>
                    </div>
                </div>

                <div class="form-control mb-4">
                    <label class="label cursor-pointer justify-start gap-3">
                        <input type="checkbox" wire:model.live="confirmRemove" class="checkbox checkbox-warning" />
                        <span class="label-text">
                            I understand this will reset {{ $removeCount }} award(s) to Earned status
                        </span>
                    </label>
                </div>

                <div class="modal-action">
                    <button wire:click="cancelConfirmation" class="btn">Cancel</button>
                    <button wire:click="bulkRemoveFromDatabase"
                            class="btn btn-warning"
                            @disabled(!$confirmRemove)>
                        Reset {{ $removeCount }} Award(s)
                    </button>
                </div>
            </div>
            <div class="modal-backdrop" wire:click="cancelConfirmation"></div>
        </div>
    @endif
</div>
