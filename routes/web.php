<?php

use App\Http\Controllers\FlashController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::resource('flashes', FlashController::class);

