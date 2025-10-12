<?php

namespace App\Http\Controllers;

use App\Models\Flash;
use Illuminate\Http\Request;

class FlashController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $flashes = Flash::with('user')
            ->latest()
            ->get();

        return view('flashes', ['flashes' => $flashes]);

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
        //
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
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Flash $flash)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Flash $flash)
    {
        //
    }
}
