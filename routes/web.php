<?php

use App\Http\Controllers\FlashController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\Register;

Route::get('/', function () {
    return view('home');
});

Route::resource('flashes', FlashController::class)
    ->only(['index', 'store', 'edit', 'update', 'destroy'])
    ->middleware('auth');

// Registration routes
Route::view('/register', 'auth.register')
    ->middleware('guest')
    ->name('register');

Route::post('/register', Register::class)
    ->middleware('guest');

