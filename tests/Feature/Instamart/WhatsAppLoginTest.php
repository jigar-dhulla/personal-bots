<?php

declare(strict_types=1);

use App\Bots\Instamart\InstamartAgent;
use App\Bots\Instamart\Models\Connection;
use App\Bots\Instamart\Swiggy\SwiggyAuth;
use App\Bots\Instamart\Tools\OrderStatusTool;
use App\Bots\Instamart\Tools\SwiggyLoginTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use JigarDhulla\LaravelWhatsApp\Services\Wacli;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

const LOGIN_OWNER = '919999999999@s.whatsapp.net';

beforeEach(function () {
    config([
        'services.swiggy.owner_jid' => LOGIN_OWNER,
        'services.swiggy.redirect_uri' => 'http://localhost:8765/callback',
    ]);

    Http::fake([
        'mcp.swiggy.com/auth/register' => Http::response(['client_id' => 'client-1']),
        'mcp.swiggy.com/auth/token' => Http::response(['access_token' => 'new-token', 'expires_in' => 432000]),
    ]);
});

function expectLoginLinkSent(int $times = 1): void
{
    $wacli = test()->mock(Wacli::class);
    $wacli->shouldReceive('send')->times($times)
        ->withArgs(fn (string $jid, string $message) => $jid === LOGIN_OWNER && str_contains($message, 'https://mcp.swiggy.com/auth/authorize?'))
        ->andReturn([true, '', '']);
}

it('sends a link on demand from the command', function () {
    Connection::factory()->create();
    expectLoginLinkSent(2);

    $this->artisan('instamart:login-link')->assertSuccessful();
    $this->artisan('instamart:login-link')->assertSuccessful();
});

it('needs an owner chat to send to', function () {
    config(['services.swiggy.owner_jid' => null]);
    test()->mock(Wacli::class)->shouldNotReceive('send');

    $this->artisan('instamart:login-link')->assertFailed();
});

it('sends the owner a link when a tool finds the login expired', function () {
    Connection::factory()->expired()->create();
    expectLoginLinkSent();
    fakeInstamart();

    $reply = (string) (new OrderStatusTool(chatJid: LOGIN_OWNER, senderJid: LOGIN_OWNER))->handle(new Request([]));

    expect($reply)->toContain('I sent the owner a login link');
});

it('sends a fresh link when the owner asks for one', function () {
    expectLoginLinkSent();

    $reply = (string) (new SwiggyLoginTool(chatJid: LOGIN_OWNER, senderJid: LOGIN_OWNER))->handle(new Request([]));

    expect($reply)->toContain('sent the login link');
});

it('finishes the login when the owner pastes the address their browser ended on', function () {
    $login = app(SwiggyAuth::class)->beginLogin();

    $reply = (string) (new SwiggyLoginTool(chatJid: LOGIN_OWNER, senderJid: LOGIN_OWNER))
        ->handle(new Request(['callback_url' => 'http://localhost:8765/callback?code=abc&state='.$login->state]));

    expect($reply)->toContain('Logged in to Swiggy');
    expect(Connection::query()->sole()->access_token)->toBe('new-token');
});

it('refuses a pasted address it did not issue a state for', function () {
    $reply = (string) (new SwiggyLoginTool(chatJid: LOGIN_OWNER, senderJid: LOGIN_OWNER))
        ->handle(new Request(['callback_url' => 'http://localhost:8765/callback?code=abc&state=forged']));

    expect($reply)->toContain('expired or was already used');
    expect(Connection::query()->count())->toBe(0);
    expect(Cache::get('instamart:login-link-sent'))->toBeNull();
});

it('only lets the owner chat renew the login', function () {
    test()->mock(Wacli::class)->shouldNotReceive('send');

    $reply = (string) (new SwiggyLoginTool(chatJid: 'group@g.us', senderJid: LOGIN_OWNER))->handle(new Request([]));

    expect($reply)->toContain('Only the owner');
    Http::assertNothingSent();
});

it('tells the agent up front when the owner is not logged in', function () {
    $agent = (new InstamartAgent)->forChat(LOGIN_OWNER, LOGIN_OWNER);

    expect((string) $agent->instructions())->toContain('The Swiggy login is NOT active');

    Connection::factory()->create();

    expect((string) $agent->instructions())->not->toContain('NOT active');
});

it('does not push the login flow in chats other than the owner chat', function () {
    $agent = (new InstamartAgent)->forChat('group@g.us', LOGIN_OWNER);

    expect((string) $agent->instructions())->not->toContain('NOT active');
});
