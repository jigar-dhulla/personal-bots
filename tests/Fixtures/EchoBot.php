<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Bots\Bot;
use Illuminate\Console\Command;

/**
 * A second bot manifest, used to prove the shared surfaces (agent config, hub,
 * admin nav, dashboard) pick up any bot registered in `config/bots.php`.
 */
class EchoBot implements Bot
{
    public function key(): string
    {
        return 'echo';
    }

    public function name(): string
    {
        return 'Echo';
    }

    public function tagline(): string
    {
        return 'Says whatever you say, right back at you.';
    }

    public function agent(): string
    {
        return EchoAgent::class;
    }

    public function envPrefix(): string
    {
        return 'ECHO';
    }

    public function routes(): ?string
    {
        return null;
    }

    public function publicRoute(): ?string
    {
        return null;
    }

    /**
     * @return array<int, class-string<Command>>
     */
    public function commands(): array
    {
        return [];
    }

    /**
     * @return array<int, array{label: string, route: string, pattern: string}>
     */
    public function adminLinks(): array
    {
        return [
            ['label' => 'Echoes', 'route' => 'failed-jobs.index', 'pattern' => 'echo.*'],
        ];
    }

    /**
     * @return array<int, array{label: string, value: int, route: string|null, note: string}>
     */
    public function adminCards(): array
    {
        return [
            ['label' => 'Echoes sent', 'value' => 42, 'route' => null, 'note' => 'Nothing to configure.'],
        ];
    }
}
