<?php

use App\Http\Controllers\Auth\ForgotPassword;
use App\Http\Controllers\Auth\Login;
use App\Http\Controllers\Auth\Logout;
use App\Http\Controllers\Auth\ResetPassword;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\LogbookController;
use App\Http\Controllers\NotFoundController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\VerifyEmailChangeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/sitemap.xml', SitemapController::class);

Route::get('/leaderboard', [LeaderboardController::class, 'index'])
    ->name('leaderboard');

// Public community statistics page
Route::view('/stats', 'stats.index')
    ->name('stats');

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

// Admin routes (award administration)
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::view('/fulfillment', 'admin.awards-dashboard')->name('admin.fulfillment');
    Route::view('/sailor-logs', 'admin.sailor-logs')->name('admin.sailor-logs');
});

// Site-operator routes (elevated tier) — site configuration
Route::middleware(['auth', 'super_admin'])->prefix('admin')->group(function () {
    Route::view('/settings', 'admin.settings')->name('admin.settings');
});

// Fallback route for 404 errors - must be last
// This ensures 404 pages go through the web middleware stack (session, auth, etc.)
// Uses controller instead of closure to support route caching
Route::fallback(NotFoundController::class);
