<?php

declare(strict_types=1);

use App\Bots\BotRegistry;
use App\Bots\Yaarpool\YaarpoolBot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\EchoBot;

uses(RefreshDatabase::class);

/**
 * Register an extra bot alongside the ones shipped in config.
 */
function withExtraBot(string $bot = EchoBot::class): void
{
    config(['bots.registered' => [...config('bots.registered'), $bot]]);
}

it('resolves every bot registered in config', function () {
    withExtraBot();

    $keys = app(BotRegistry::class)->all()->map(fn ($bot) => $bot->key())->all();

    expect($keys)->toBe(['yaarpool', 'echo']);
});

it('finds a bot by key and returns null for an unknown one', function () {
    $registry = app(BotRegistry::class);

    expect($registry->find('yaarpool'))->toBeInstanceOf(YaarpoolBot::class)
        ->and($registry->find('nope'))->toBeNull();
});

it('finds the bot that owns a hostname, ignoring case and a leading www', function () {
    config(['bots.domains' => ['yaarpool' => 'rideshare.ing']]);

    $registry = app(BotRegistry::class);

    expect($registry->forDomain('rideshare.ing'))->toBeInstanceOf(YaarpoolBot::class)
        ->and($registry->forDomain('WWW.Rideshare.ING'))->toBeInstanceOf(YaarpoolBot::class)
        ->and($registry->forDomain('bots.jigardhulla.dev'))->toBeNull()
        ->and($registry->forDomain('myrideshare.ing'))->toBeNull();
});

it('lists every registered bot on the public hub', function () {
    withExtraBot();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Yaarpool')
        ->assertSee('Carpool with your group')
        ->assertSee('Echo')
        ->assertSee('Says whatever you say, right back at you.')
        ->assertSee(route('yaarpool.home'), false);
});

it('adds every registered bot to the admin nav', function () {
    withExtraBot();

    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Rides')
        ->assertSee('Group settings')
        ->assertSee('Echoes');
});

it('gives every registered bot its own section of dashboard cards', function () {
    withExtraBot();

    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Upcoming rides')
        ->assertSee('Echoes sent')
        ->assertSee('Nothing to configure.');
});
