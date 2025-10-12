<?php

use App\Http\Controllers\FlashController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/flashes', [FlashController::class, 'index'])->name('flashes.index');

