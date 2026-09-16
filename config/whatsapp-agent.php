<?php

declare(strict_types=1);
use App\Bots\Bot;

$csv = static fn (?string $value): array => array_values(array_filter(
    array_map('trim', explode(',', (string) $value)),
    static fn (string $item): bool => $item !== '',
));

return [

    /*
    |--------------------------------------------------------------------------
    | wacli Integration
    |--------------------------------------------------------------------------
    |
    | Paths to the wacli binary and its sqlite database. Both are typically
    | populated by `php artisan wa:setup`, which auto-detects them via
    | `wacli doctor --json` and falls back to prompting the user.
    |
    */

    'wacli' => [
        'binary' => env('WA_WACLI_BINARY', 'wacli'),
        'database' => env('WA_WACLI_DATABASE'),
        'store' => env('WA_WACLI_STORE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Agents
    |--------------------------------------------------------------------------
    |
    | Each entry defines one AI agent. A message is dispatched to every agent
    | whose scope (chats + groups) contains the sender's JID and whose triggers
    | match the message body.
    |
    | This table is derived from the bots registered in `config/bots.php` — one
    | entry per bot, scoped by that bot's env prefix — so a new bot needs no
    | edit here. To run several agents for one bot, or an agent with no bot
    | manifest behind it, append literal entries to the array below.
    |
    | - agent:    Laravel AI SDK Agent Class. Configure provider, model, and
    |             system prompt via the class's attributes and methods. See:
    |             https://laravel.com/docs/13.x/ai-sdk#agent-configuration
    | - triggers: Phrases that activate this agent (case-insensitive substring
    |             match). Empty array matches every message in scope.
    | - chats:    DM JIDs this agent listens to. MUST be non-empty unless
    |             groups is non-empty — empty scope means the agent is inactive.
    | - groups:   Group JIDs this agent listens to.
    |
    | The union of all agents' chats + groups determines which JIDs are polled.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Bot Contact
    |--------------------------------------------------------------------------
    |
    | The bot's WhatsApp number, in international format with no spaces, dashes,
    | or leading "+" (e.g. 15551234567). Used to build wa.me invite links on
    | the marketing site. This is a personal number, so it lives only in .env
    | and must never be committed to source control.
    |
    */

    'number' => preg_replace('/\D/', '', (string) env('WA_PHONE_NUMBER')),

    'agents' => array_values(array_map(static function (string $manifest) use ($csv): array {
        /** @var Bot $bot */
        $bot = new $manifest;
        $prefix = $bot->envPrefix();

        return [
            'agent' => $bot->agent(),
            // CSV of trigger phrases. wa:status reveals the account JID for @mention triggers (e.g., @123456789)
            'triggers' => $csv(env($prefix.'_TRIGGERS')),
            'chats' => $csv(env($prefix.'_CHATS')),   // CSV of DM JIDs. run `php artisan wa:chats`
            'groups' => $csv(env($prefix.'_GROUPS')), // CSV of group JIDs. run `php artisan wa:groups`
        ];
    }, (array) (require __DIR__.'/bots.php')['registered'])),

    /*
    |--------------------------------------------------------------------------
    | Polling
    |--------------------------------------------------------------------------
    |
    | How often the message reader polls wacli's sqlite database for new
    | messages, in seconds.
    |
    */

    'polling' => [
        'interval_seconds' => (int) env('WA_POLLING_INTERVAL', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversation History
    |--------------------------------------------------------------------------
    |
    | Maximum number of past messages included in each agent's context window.
    | The most recent N messages are fetched and provided as conversation
    | history. Higher values give the agent more context but consume more
    | tokens per request.
    |
    */

    'conversation' => [
        'history_limit' => (int) env('WA_HISTORY_LIMIT', 100),
    ],

];
