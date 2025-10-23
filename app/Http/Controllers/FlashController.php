<?php

namespace App\Http\Controllers;

use App\Models\Flash;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class FlashController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user();
        $currentYear = now()->year;

        $flashes = $user->flashes()
            ->orderBy('date', 'desc')
            ->paginate(15);

        // Calculate current year progress: sailing days + non-sailing days (capped at 5)
        $stats = $user->flashStatsForYear($currentYear);

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

        // Get existing dates for the user within selectable range (for disabling in date picker)
        // Current year + previous year (if before Feb 1st grace period)
        $now = now();
        $minDate = $this->getMinAllowedDate($now);
        $maxDate = $now->copy()->addDay();

        $existingDates = $user->flashes()
            ->where('date', '>=', $minDate)
            ->where('date', '<=', $maxDate)
            ->pluck('date')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->toArray();

        return view('flashes.index', [
            'flashes' => $flashes,
            'totalFlashes' => $stats->total,
            'sailingCount' => $stats->sailing,
            'nonSailingCount' => min($stats->nonSailing, 5),
            'nextMilestone' => $nextMilestone,
            'earnedAwards' => $earnedAwards,
            'currentYear' => $currentYear,
            'existingDates' => $existingDates,
            'minDate' => $minDate->format('Y-m-d'),
            'maxDate' => $maxDate->format('Y-m-d'),
        ]);

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Handle multiple dates for bulk creation
        // Determine minimum allowed date based on grace period
        $now = now();
        $minDate = $this->getMinAllowedDate($now);
        $maxDate = $now->copy()->addDay()->format('Y-m-d');

        $request->validate([
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
                function ($attribute, $value, $fail) use ($request) {
                    // Enforce null for non-sailing activities
                    if ($request->activity_type !== 'sailing' && $value !== null) {
                        $fail('Sailing type must be empty for non-sailing activities.');
                    }
                },
            ],
            'location' => 'nullable|string|max:255',
            'sail_number' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        $dates = $request->dates;

        // Check for duplicate dates before creating any (database-level filtering)
        $existingDates = auth()->user()->flashes()
            ->whereIn(\DB::raw('DATE(date)'), $dates)
            ->pluck('date')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->toArray();

        if (! empty($existingDates)) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['dates' => 'You already have activities logged for: '.implode(', ', $existingDates).'. Please remove these dates or edit existing entries.']);
        }

        // Prepare common data for all flashes
        $commonData = $request->only([
            'activity_type',
            'event_type',
            'location',
            'sail_number',
            'notes',
        ]);

        // Use transaction to ensure all-or-nothing
        \DB::transaction(function () use ($dates, $commonData) {
            foreach ($dates as $date) {
                auth()->user()->flashes()->create(array_merge($commonData, ['date' => $date]));
            }
        });

        // Check if this is a non-sailing activity and if they've reached the limit
        $hasWarning = false;
        if (in_array($request->activity_type, ['maintenance', 'race_committee'])) {
            $currentYear = now()->year;
            $stats = auth()->user()->flashStatsForYear($currentYear);

            if ($stats->nonSailing > 5) {
                $hasWarning = true;
            }
        }

        $count = count($dates);
        $message = $count === 1 ? 'Flash logged successfully!' : "{$count} flashes logged successfully!";

        if ($hasWarning) {
            return redirect()->route('flashes.index')->with('warning', "Non-sailing days logged! Heads up: You've already got 5 non-sailing days counting toward awards. Keep logging though—we want to see all your Lightning time!");
        }

        return redirect()->route('flashes.index')->with('success', $message);
    }

    /**
     * Display the specified resource.
     */
    public function show(Flash $flash)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Flash $flash)
    {
        $this->authorize('update', $flash);

        return view('flashes.edit', compact('flash'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Flash $flash)
    {
        $this->authorize('update', $flash);

        $request->validate([
            'date' => [
                'required',
                'date',
                'before_or_equal:'.now()->addDay()->format('Y-m-d'),
            ],
            'activity_type' => 'required|in:sailing,maintenance,race_committee',
            'event_type' => [
                'required_if:activity_type,sailing',
                'nullable',
                'in:regatta,club_race,practice,leisure',
                function ($attribute, $value, $fail) use ($request) {
                    // Enforce null for non-sailing activities
                    if ($request->activity_type !== 'sailing' && $value !== null) {
                        $fail('Sailing type must be empty for non-sailing activities.');
                    }
                },
            ],
            'location' => 'nullable|string|max:255',
            'sail_number' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        // Check for duplicate date (excluding current flash)
        $exists = auth()->user()->flashes()
            ->whereDate('date', $request->date)
            ->where('id', '!=', $flash->id)
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['date' => 'You already have an activity logged for this date. Please choose a different date.']);
        }

        $validated = $request->only([
            'date',
            'activity_type',
            'event_type',
            'location',
            'sail_number',
            'notes',
        ]);

        $flash->update($validated);

        return redirect()->route('flashes.index')->with('success', 'Flash updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Flash $flash)
    {
        $this->authorize('delete', $flash);

        $flash->delete();

        return redirect()->route('flashes.index')->with('success', 'Flash deleted!');
    }

    /**
     * Calculate the minimum allowed date based on grace period logic.
     * January allows previous year entries, February onward restricts to current year.
     */
    private function getMinAllowedDate(\Carbon\Carbon $now): \Carbon\Carbon
    {
        $minDate = $now->copy()->startOfYear();
        if ($now->month === 1) {
            // January: allow previous year entries (grace period)
            $minDate = $now->copy()->subYear()->startOfYear();
        }

        return $minDate;
    }
}
