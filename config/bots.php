<?php

declare(strict_types=1);

use App\Bots\Yaarpool\YaarpoolBot;

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

    'registered' => [
        YaarpoolBot::class,
    ],

];
