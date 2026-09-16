<?php

declare(strict_types=1);

namespace App\Bots;

use Illuminate\Console\Command;

/**
 * The manifest for one bot running on this WhatsApp number.
 *
 * A bot bundles an AI agent (which handles inbound WhatsApp messages) with
 * whatever web surface it needs — a public page, admin screens, artisan
 * commands. Registering the manifest in `config/bots.php` is the single step
 * that wires all of it up: WhatsApp routing, routes, nav, and dashboard.
 *
 * Implementations must be constructible with no arguments — `config/whatsapp-agent.php`
 * instantiates them while building the agent table, before the container boots.
 */
interface Bot
{
    /**
     * URL-safe key used for route names, URL segments, and view namespaces.
     */
    public function key(): string;

    /**
     * Display name shown on the public hub and in the admin nav.
     */
    public function name(): string;

    /**
     * One-line summary of what this bot does, shown on the public hub.
     */
    public function tagline(): string;

    /**
     * FQCN of the Laravel AI agent that handles this bot's WhatsApp messages.
     *
     * @return class-string
     */
    public function agent(): string;

    /**
     * Prefix for this bot's env keys: <PREFIX>_TRIGGERS, <PREFIX>_CHATS,
     * <PREFIX>_GROUPS. Each is a CSV list read by `config/whatsapp-agent.php`.
     */
    public function envPrefix(): string;

    /**
     * Absolute path to the bot's route file, or null when it has no web surface.
     */
    public function routes(): ?string;

    /**
     * Route name of the bot's public landing page, or null when it has none.
     */
    public function publicRoute(): ?string;

    /**
     * Artisan commands this bot ships.
     *
     * @return array<int, class-string<Command>>
     */
    public function commands(): array;

    /**
     * Links to this bot's admin screens. `pattern` is passed to
     * `request()->routeIs()` to decide which link is highlighted.
     *
     * @return array<int, array{label: string, route: string, pattern: string}>
     */
    public function adminLinks(): array;

    /**
     * Live stat cards rendered on the admin dashboard. Queried per request, so
     * keep these cheap.
     *
     * @return array<int, array{label: string, value: int, route: string|null, note: string}>
     */
    public function adminCards(): array;
}
