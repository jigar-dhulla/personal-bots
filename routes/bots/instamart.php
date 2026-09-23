<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::prefix('instamart')->name('instamart.')->group(function () {
    Route::view('/', 'instamart.home')->name('home');
});
