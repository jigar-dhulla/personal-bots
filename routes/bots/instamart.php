<?php

declare(strict_types=1);

use App\Bots\Instamart\Http\Controllers\SwiggyLoginController;
use Illuminate\Support\Facades\Route;

Route::prefix('instamart')->name('instamart.')->group(function () {
    Route::view('/', 'instamart.home')->name('home');

    /** Swiggy's OAuth redirect lands here; it must match SWIGGY_REDIRECT_URI exactly. */
    Route::get('/callback', [SwiggyLoginController::class, 'callback'])->middleware('auth')->name('callback');
});

Route::prefix('admin/instamart')->name('instamart.')->middleware('auth')->group(function () {
    Route::get('/login', [SwiggyLoginController::class, 'start'])->name('login');
});
