<?php

namespace App\Providers;

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
     * Wire up whatever the registered bots contribute to the shared app: their
     * artisan commands (which live outside `app/Console/Commands` and so are
     * not auto-discovered) and the admin nav. The wa.me invite link is shared
     * with every view so no bot has to rebuild it for its landing page.
     */
    public function boot(BotRegistry $bots): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands($bots->all()->flatMap->commands()->all());
        }

        View::composer(
            'components.admin.layout',
            fn (ViewContract $view) => $view->with('bots', $bots->all()),
        );

        View::composer('*', function (ViewContract $view): void {
            $number = config('whatsapp-agent.number');

            $view->with('whatsappInviteUrl', $number ? 'https://wa.me/'.$number : null);
        });
    }
}
