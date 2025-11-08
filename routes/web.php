<?php

use App\Http\Controllers\Api\FleetController;
use App\Http\Controllers\Auth\ForgotPassword;
use App\Http\Controllers\Auth\Login;
use App\Http\Controllers\Auth\Logout;
use App\Http\Controllers\Auth\ResetPassword;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\LogbookController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\VerifyEmailChangeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/sitemap.xml', SitemapController::class);

Route::get('/leaderboard', [LeaderboardController::class, 'index'])
    ->name('leaderboard');

// API routes for fleets and districts
// Cache for 1 hour, then revalidate with ETag (balances freshness and performance)
// No rate limiting needed - these are lightweight read-only endpoints with browser caching
Route::prefix('api')->middleware(['cache.headers:public;max_age=3600;etag'])->group(function () {
    Route::get('/districts-and-fleets', [FleetController::class, 'districtsAndFleets']);
});

// Logbook routes - Note: store/update/destroy are handled by Livewire components
// Only index and edit use traditional routes
Route::resource('logbook', LogbookController::class)
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

// Registration routes (handled by Livewire component)
Route::view('/register', 'auth.register')
    ->middleware('guest')
    ->name('register');

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

// Password Reset routes
Route::view('/password/reset', 'auth.forgot-password')
    ->middleware('guest')
    ->name('password.request');

Route::post('/password/email', ForgotPassword::class)
    ->middleware('guest')
    ->name('password.email');

Route::view('/password/reset/{token}', 'auth.reset-password')
    ->middleware('guest')
    ->name('password.reset');

Route::post('/password/reset', ResetPassword::class)
    ->middleware('guest')
    ->name('password.update');

// Email verification route
Route::get('/verify-email/{token}', VerifyEmailChangeController::class)
    ->name('verify-email-change');

// Admin routes
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::view('/fulfillment', 'admin.awards-dashboard')->name('admin.fulfillment');
    Route::view('/sailor-logs', 'admin.sailor-logs')->name('admin.sailor-logs');
});

// Fallback route for 404 errors - must be last
// This ensures 404 pages go through the web middleware stack (session, auth, etc.)
// Uses controller instead of closure to support route caching
Route::fallback(\App\Http\Controllers\NotFoundController::class);
