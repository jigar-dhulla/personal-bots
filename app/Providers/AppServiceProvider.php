<?php

namespace App\Providers;

use App\Bots\Bot;
use App\Bots\BotRegistry;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BotRegistry::class);
    }

    /**
     * Wire up whatever the registered bots contribute to the shared app:
     * their artisan commands (which live outside `app/Console/Commands` and
     * so are not auto-discovered) and the admin nav.
     */
    public function boot(BotRegistry $bots): void
    {
        $bots->all()->each(fn (Bot $bot) => $this->commands($bot->commands()));

        View::composer(
            'components.admin.layout',
            fn (ViewContract $view) => $view->with('bots', app(BotRegistry::class)->all()),
        );
    }
}
