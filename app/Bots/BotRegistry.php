<?php

declare(strict_types=1);

namespace App\Bots;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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

    /**
     * The bot that owns the given hostname, or null when no bot claims it.
     *
     * A bot's domain is its own front door: "/" there opens that bot's
     * landing page instead of the hub's roster. A leading "www." matches the
     * bare domain, since both usually point at the same place.
     */
    public function forDomain(string $host): ?Bot
    {
        $host = Str::of($host)->lower()->chopStart('www.')->value();

        $key = array_search($host, (array) config('bots.domains', []), true);

        return $key === false ? null : $this->find((string) $key);
    }
}
