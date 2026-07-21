<div class="max-w-2xl mx-auto">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-3xl font-bold text-primary">Settings</h1>
        <label class="flex items-center gap-2" for="admin-settings-year">
            <span class="text-sm font-medium">Year</span>
            <select id="admin-settings-year" wire:model.live="selectedYear" class="select select-bordered select-sm w-28">
                @foreach ($availableYears as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="card bg-base-100 shadow-md">
        <div class="card-body">
            <h2 class="card-title text-lg">Community goal for {{ $selectedYear }}</h2>
            <p class="text-sm opacity-70">
                The annual target for total community sailing days, shown as the
                lightning fill-up on the public <a href="/stats" class="link link-primary">Community Stats</a> page.
                Leave blank to unset the goal.
            </p>

            <form wire:submit="save" class="mt-4 space-y-4">
                <div>
                    <label class="label" for="community-goal">
                        <span class="label-text font-medium">Goal (community sailing days)</span>
                    </label>
                    <input type="number"
                           id="community-goal"
                           wire:model="goal"
                           min="1"
                           max="1000000"
                           step="1"
                           placeholder="e.g. 2000"
                           class="input input-bordered w-full max-w-xs @error('goal') input-error @enderror">
                    @error('goal')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="text-sm opacity-70">
                    Community total so far in {{ $selectedYear }}:
                    <span class="font-bold">{{ number_format($currentTotal) }}</span> qualifying days
                    @if ($goal && $goal > 0)
                        — <span class="font-bold">{{ (int) round(min(100, $currentTotal / $goal * 100)) }}%</span> of this goal
                    @endif
                </div>

                @if ($priorTotal > 0)
                    <div class="text-sm opacity-70">
                        For reference, {{ $priorYear }} finished at {{ number_format($priorTotal) }} days @if ($priorIsHistorical)(from the pre-launch process)@endif
                    </div>
                @endif

                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Save goal</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            </form>
        </div>
    </div>
</div>
