<?php

use App\Http\Controllers\Api\FleetController;
use App\Http\Controllers\Auth\Login;
use App\Http\Controllers\Auth\Logout;
use App\Http\Controllers\Auth\Register;
use App\Http\Controllers\ExportController;
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

// Logbook routes - Note: store/update/destroy are handled by Livewire components
// Only index and edit use traditional routes
Route::resource('logbook', FlashController::class)
    ->only(['index', 'edit'])
    ->middleware('auth');

// Profile route
Route::view('/profile', 'profile')
    ->middleware('auth')
    ->name('profile');

// Export route
Route::get('/export/user-data', [ExportController::class, 'exportUserData'])
    ->middleware('auth')
    ->name('export.user-data');

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

// Logout routes
Route::post('/logout', Logout::class)
    ->middleware('auth')
    ->name('logout');

// Handle GET requests to logout (redirect to home)
Route::get('/logout', function () {
    return redirect('/');
});

// Fallback route for 404 errors - must be last
// This ensures 404 pages go through the web middleware stack (session, auth, etc.)
Route::fallback(function () {
    return response()->view('errors.404', [], 404);
});
