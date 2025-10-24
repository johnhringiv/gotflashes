<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LeaderboardController extends Controller
{
    /**
     * Display the leaderboard page.
     * All logic is now handled by the Livewire component.
     */
    public function index(): View
    {
        return view('leaderboard.index');
    }
}
