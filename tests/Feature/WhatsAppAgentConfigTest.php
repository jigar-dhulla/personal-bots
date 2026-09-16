<?php

declare(strict_types=1);

use App\Bots\Bot;
use App\Bots\Yaarpool\YaarpoolAgent;

/**
 * Evaluate `config/whatsapp-agent.php` from scratch with the given env in
 * place, and return the agent entry at $index. The file derives its agent
 * table from `config/bots.php`, so this exercises the real wiring.
 */
function loadAgentsConfig(array $env, int $index = 0): array
{
    foreach ($env as $key => $value) {
        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        } else {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    return (require base_path('config/whatsapp-agent.php'))['agents'][$index];
}

afterEach(function () {
    foreach (['YAARPOOL_TRIGGERS', 'YAARPOOL_CHATS', 'YAARPOOL_GROUPS'] as $key) {
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }
});

it('derives one agent entry per registered bot', function () {
    $registered = (array) (require config_path('bots.php'))['registered'];
    $agents = (require base_path('config/whatsapp-agent.php'))['agents'];

    expect($agents)->toHaveCount(count($registered));

    foreach ($registered as $index => $manifest) {
        /** @var Bot $bot */
        $bot = new $manifest;

        expect($agents[$index]['agent'])->toBe($bot->agent());
    }
});

it('returns empty arrays when env vars are unset', function () {
    $agent = loadAgentsConfig([
        'YAARPOOL_TRIGGERS' => null,
        'YAARPOOL_CHATS' => null,
        'YAARPOOL_GROUPS' => null,
    ]);

    expect($agent['agent'])->toBe(YaarpoolAgent::class)
        ->and($agent['triggers'])->toBe([])
        ->and($agent['chats'])->toBe([])
        ->and($agent['groups'])->toBe([]);
});

it('returns empty arrays for empty strings', function () {
    $agent = loadAgentsConfig([
        'YAARPOOL_TRIGGERS' => '',
        'YAARPOOL_CHATS' => '',
        'YAARPOOL_GROUPS' => '',
    ]);

    expect($agent['triggers'])->toBe([])
        ->and($agent['chats'])->toBe([])
        ->and($agent['groups'])->toBe([]);
});

it('parses CSV values, trims whitespace, and drops blank entries', function () {
    $agent = loadAgentsConfig([
        'YAARPOOL_TRIGGERS' => '@123, @456 ,  ,  ',
        'YAARPOOL_CHATS' => '111@s.whatsapp.net,222@s.whatsapp.net',
        'YAARPOOL_GROUPS' => '  120363409213306573@g.us ,, 999@g.us',
    ]);

    expect($agent['triggers'])->toBe(['@123', '@456'])
        ->and($agent['chats'])->toBe(['111@s.whatsapp.net', '222@s.whatsapp.net'])
        ->and($agent['groups'])->toBe(['120363409213306573@g.us', '999@g.us']);
});

it('parses a single value without commas', function () {
    $agent = loadAgentsConfig([
        'YAARPOOL_GROUPS' => '120363409213306573@g.us',
    ]);

    expect($agent['groups'])->toBe(['120363409213306573@g.us']);
});

it('scopes each bot to its own env prefix', function () {
    $agent = loadAgentsConfig([
        'YAARPOOL_TRIGGERS' => '@yaarpool',
        'ECHO_TRIGGERS' => '@echo',
    ]);

    expect($agent['triggers'])->toBe(['@yaarpool']);

    unset($_ENV['ECHO_TRIGGERS'], $_SERVER['ECHO_TRIGGERS']);
    putenv('ECHO_TRIGGERS');
});
