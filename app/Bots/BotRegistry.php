<?php

declare(strict_types=1);

namespace App\Bots;

use Illuminate\Support\Collection;

/**
 * Resolves the bot manifests listed in `config/bots.php`.
 *
 * Everything shared between bots — the public hub, the admin nav and
 * dashboard, route and command registration — reads the roster from here
 * rather than hard-coding any one bot.
 */
class BotRegistry
{
    /**
     * Every registered bot, in the order listed in config.
     *
     * Resolved on each call rather than memoized: manifests are cheap value
     * objects, and re-reading config keeps the roster honest when it changes
     * mid-request (as it does in tests).
     *
     * @return Collection<int, Bot>
     */
    public function all(): Collection
    {
        return (new Collection((array) config('bots.registered', [])))
            ->map(fn (string $bot): Bot => app($bot))
            ->values();
    }

    /**
     * The bot with the given key, or null when it is not registered.
     */
    public function find(string $key): ?Bot
    {
        return $this->all()->first(fn (Bot $bot): bool => $bot->key() === $key);
    }
}
