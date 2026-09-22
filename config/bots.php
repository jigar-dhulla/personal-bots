<?php

declare(strict_types=1);

use App\Bots\Bot;
use App\Bots\Instamart\InstamartBot;
use App\Bots\Yaarpool\YaarpoolBot;

$registered = [
    YaarpoolBot::class,
    InstamartBot::class,
];

return [

    /*
    |--------------------------------------------------------------------------
    | Registered Bots
    |--------------------------------------------------------------------------
    |
    | One WhatsApp number, many bots. Each entry is an App\Bots\Bot manifest
    | describing a bot's agent, its env prefix (<PREFIX>_TRIGGERS / _CHATS /
    | _GROUPS), its routes, and the admin screens it contributes.
    |
    | Registering a manifest here is the only wiring step: config/whatsapp-agent.php
    | builds its agent table from this list, routes/web.php loads each bot's
    | route file, and the hub, admin nav, and dashboard read the same roster.
    |
    | Bots appear on the public hub and in the admin nav in the order listed.
    |
    */

    'registered' => $registered,

    /*
    |--------------------------------------------------------------------------
    | Bot Domains
    |--------------------------------------------------------------------------
    |
    | A bot may own a hostname of its own. Visiting "/" on that host opens
    | the bot's landing page instead of the hub's roster — the hub keeps its
    | own domain. Derived by convention from each bot's key (<KEY>_DOMAIN),
    | so claiming one is an env change, never a code change. A leading "www."
    | is matched too. Bots with no domain simply don't appear here.
    |
    */

    'domains' => array_filter(array_reduce($registered, static function (array $domains, string $manifest): array {
        /** @var Bot $bot */
        $bot = new $manifest;

        $domains[$bot->key()] = strtolower(trim((string) env(strtoupper($bot->key()).'_DOMAIN')));

        return $domains;
    }, [])),

];
