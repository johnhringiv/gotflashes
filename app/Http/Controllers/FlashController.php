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

        return view('flashes.index', [
            'flashes' => $flashes,
            'totalFlashes' => $stats->total,
            'sailingCount' => $stats->sailing,
            'nonSailingCount' => min($stats->nonSailing, 5),
            'nextMilestone' => $nextMilestone,
            'earnedAwards' => $earnedAwards,
            'currentYear' => $currentYear,
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

        // Check for duplicate date
        $exists = auth()->user()->flashes()
            ->whereDate('date', $request->date)
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['date' => 'You already have an activity logged for this date. Please edit the existing entry or choose a different date.']);
        }

        $validated = $request->only([
            'date',
            'activity_type',
            'event_type',
            'location',
            'sail_number',
            'notes',
        ]);

        // Use the authenticated user
        auth()->user()->flashes()->create($validated);

        return redirect()->route('flashes.index')->with('success', 'Flash logged successfully!');
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
}
