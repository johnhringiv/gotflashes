<?php

use App\Http\Controllers\Api\FleetController;
use App\Http\Controllers\Auth\Login;
use App\Http\Controllers\Auth\Logout;
use App\Http\Controllers\Auth\Register;
use App\Http\Controllers\FlashController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/sitemap.xml', SitemapController::class);

Route::get('/leaderboard', [LeaderboardController::class, 'index'])
    ->name('leaderboard');

// API routes for fleets and districts
// Cache for 1 hour, then revalidate with ETag (balances freshness and performance)
Route::prefix('api')->middleware(['throttle:60,1', 'cache.headers:public;max_age=3600;etag'])->group(function () {
    Route::get('/districts', [FleetController::class, 'districts']);
    Route::get('/fleets', [FleetController::class, 'fleets']);
    Route::get('/districts/{districtId}/fleets', [FleetController::class, 'fleetsByDistrict']);
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

// Login routes
Route::view('/login', 'auth.login')
    ->middleware('guest')
    ->name('login');

Route::post('/login', Login::class)
    ->middleware('guest');

// Logout route
Route::post('/logout', Logout::class)
    ->middleware('auth')
    ->name('logout');
