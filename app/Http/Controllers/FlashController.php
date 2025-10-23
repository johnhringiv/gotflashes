<?php

namespace App\Http\Controllers;

use App\Models\Flash;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class FlashController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     * All CRUD operations are handled by Livewire components (FlashForm, FlashList).
     */
    public function index()
    {
        // All data now handled by Livewire components
        return view('flashes.index');
    }

    /**
     * Show the form for editing the specified resource.
     * The form itself is a Livewire component, but we need this route to load the edit page.
     */
    public function edit(Flash $flash)
    {
        $this->authorize('update', $flash);

        // Check if flash is within editable date range
        $now = now();
        $minDate = $this->getMinAllowedDate($now);
        $maxDate = $now->copy()->addDay();

        if (! $flash->isEditable($minDate, $maxDate)) {
            abort(403, 'This activity is outside the editable date range.');
        }

        // Get existing dates for date picker (to disable duplicates)
        $user = auth()->user();
        $existingDates = $user->flashes()
            ->where('date', '>=', $minDate)
            ->where('date', '<=', $maxDate)
            ->pluck('date')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->toArray();

        return view('flashes.edit', compact('flash', 'minDate', 'maxDate', 'existingDates'));
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
