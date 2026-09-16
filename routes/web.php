<?php

use App\Bots\Bot;
use App\Bots\BotRegistry;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\FailedJobsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function (BotRegistry $bots) {
    $number = config('whatsapp-agent.number');

    return view('welcome', [
        'bots' => $bots->all(),
        'whatsappInviteUrl' => $number ? 'https://wa.me/'.$number : null,
    ]);
})->name('home');

Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [LoginController::class, 'create'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

        Route::get('/failed-jobs', [FailedJobsController::class, 'index'])->name('failed-jobs.index');
        Route::post('/failed-jobs/{uuid}/retry', [FailedJobsController::class, 'retry'])->name('failed-jobs.retry');
        Route::post('/failed-jobs/flush', [FailedJobsController::class, 'flush'])->name('failed-jobs.flush');
    });
});

/*
|--------------------------------------------------------------------------
| Bot Routes
|--------------------------------------------------------------------------
|
| Every bot registered in `config/bots.php` contributes its own public page
| and admin screens. Each route file is loaded with the bot's manifest
| available as `$bot`.
|
*/

/** @var Bot $bot */
foreach (app(BotRegistry::class)->all() as $bot) {
    $routes = $bot->routes();

    if ($routes !== null && file_exists($routes)) {
        require $routes;
    }
}
