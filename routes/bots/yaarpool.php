<?php

declare(strict_types=1);

use App\Bots\Yaarpool\Http\Controllers\GroupSettingsController;
use App\Bots\Yaarpool\Http\Controllers\RidesController;
use App\Bots\Yaarpool\Http\Controllers\UserSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('yaarpool')->name('yaarpool.')->group(function () {
    Route::view('/', 'yaarpool.home')->name('home');
    Route::view('/usage', 'yaarpool.usage')->name('usage');
});

/** Links published before the site became a multi-bot hub pointed at /usage. */
Route::permanentRedirect('/usage', '/yaarpool/usage');

Route::prefix('admin/yaarpool')->name('yaarpool.')->middleware('auth')->group(function () {
    Route::get('/rides', [RidesController::class, 'index'])->name('rides.index');
    Route::get('/rides/{ride}', [RidesController::class, 'show'])->name('rides.show');
    Route::delete('/rides/{ride}', [RidesController::class, 'destroy'])->name('rides.destroy');

    Route::get('/group-settings', [GroupSettingsController::class, 'index'])->name('group-settings.index');
    Route::post('/group-settings', [GroupSettingsController::class, 'store'])->name('group-settings.store');
    Route::put('/group-settings/{groupSetting}', [GroupSettingsController::class, 'update'])->name('group-settings.update');
    Route::delete('/group-settings/{groupSetting}', [GroupSettingsController::class, 'destroy'])->name('group-settings.destroy');

    Route::get('/user-settings', [UserSettingsController::class, 'index'])->name('user-settings.index');
    Route::post('/user-settings', [UserSettingsController::class, 'store'])->name('user-settings.store');
    Route::put('/user-settings/{userSetting}', [UserSettingsController::class, 'update'])->name('user-settings.update');
    Route::delete('/user-settings/{userSetting}', [UserSettingsController::class, 'destroy'])->name('user-settings.destroy');
});
