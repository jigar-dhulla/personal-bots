<?php

declare(strict_types=1);

use App\Bots\Instamart\Models\ChatAddress;
use App\Bots\Instamart\Models\Connection;
use App\Bots\Instamart\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('forgets addresses, orders and cached state for one chat only', function () {
    ChatAddress::factory()->create(['chat_jid' => 'a@s.whatsapp.net']);
    ChatAddress::factory()->create(['chat_jid' => 'b@s.whatsapp.net']);
    Order::factory()->create(['chat_jid' => 'a@s.whatsapp.net']);
    Order::factory()->create(['chat_jid' => 'b@s.whatsapp.net']);
    Cache::put('instamart:picks:a@s.whatsapp.net', [1 => ['spinId' => 's']]);
    Cache::put('instamart:pending-order:a@s.whatsapp.net', ['sender' => 'a']);

    $this->artisan('instamart:forget --chat=a@s.whatsapp.net --force')
        ->expectsOutputToContain('Deleted 1 address(es) and 1 order record(s) for chat a@s.whatsapp.net')
        ->assertSuccessful();

    expect(ChatAddress::query()->pluck('chat_jid')->all())->toBe(['b@s.whatsapp.net'])
        ->and(Order::query()->pluck('chat_jid')->all())->toBe(['b@s.whatsapp.net'])
        ->and(Cache::has('instamart:picks:a@s.whatsapp.net'))->toBeFalse()
        ->and(Cache::has('instamart:pending-order:a@s.whatsapp.net'))->toBeFalse();
});

it('keeps everything when the prompt is declined', function () {
    ChatAddress::factory()->create();
    Order::factory()->create();

    $this->artisan('instamart:forget')
        ->expectsConfirmation('Delete 1 saved address(es) and 1 order record(s) for every chat?', 'no')
        ->expectsOutputToContain('Nothing deleted.')
        ->assertSuccessful();

    expect(ChatAddress::query()->count())->toBe(1)->and(Order::query()->count())->toBe(1);
});

it('clears saved chat addresses on logout', function () {
    Connection::factory()->create();
    ChatAddress::factory()->count(2)->create();
    Http::fake(['mcp.swiggy.com/auth/logout' => Http::response([], 200)]);

    $this->artisan('instamart:logout')
        ->expectsOutputToContain('Logged out of Swiggy. Cleared 2 saved chat address(es).')
        ->assertSuccessful();

    expect(ChatAddress::query()->count())->toBe(0)->and(Connection::query()->count())->toBe(0);
});
