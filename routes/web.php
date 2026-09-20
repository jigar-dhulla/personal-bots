<?php

use App\Bots\Bot;
use App\Bots\BotRegistry;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\FailedJobsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * The hub lists every bot — unless this host belongs to one bot, in which
 * case "/" is that bot's front door and opens its landing page.
 */
Route::get('/', function (Request $request, BotRegistry $bots) {
    $bot = $bots->forDomain($request->getHost());

    if ($bot instanceof Bot && Route::has("{$bot->key()}.home")) {
        return redirect()->route("{$bot->key()}.home");
    }

    return view('welcome', ['bots' => $bots->all()]);
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
| and admin screens from `routes/bots/<key>.php`. A bot with no web surface
| simply ships no such file.
|
*/

/** @var Bot $bot */
foreach (app(BotRegistry::class)->all() as $bot) {
    $routes = base_path("routes/bots/{$bot->key()}.php");

    if (file_exists($routes)) {
        require $routes;
    }
}
