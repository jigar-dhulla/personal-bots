<?php

declare(strict_types=1);

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
    $wacli = mock(Wacli::class);
    $wacli->shouldReceive('send')->times($times)
        ->withArgs(fn (string $jid, string $message) => $jid === LOGIN_OWNER && str_contains($message, 'https://mcp.swiggy.com/auth/authorize?'))
        ->andReturn([true, '', '']);
}

it('sends the owner a login link when the token is missing', function () {
    expectLoginLinkSent();

    $this->artisan('instamart:login-link --if-needed')->expectsOutputToContain('Login link sent')->assertSuccessful();
});

it('sends a link when the token is about to expire but not while it has days left', function () {
    Connection::factory()->create(['expires_at' => now()->addDays(4)]);
    mock(Wacli::class)->shouldNotReceive('send');

    $this->artisan('instamart:login-link --if-needed')->assertSuccessful();

    Connection::query()->delete();
    Connection::factory()->create(['expires_at' => now()->addHours(6)]);
    expectLoginLinkSent();

    $this->artisan('instamart:login-link --if-needed')->assertSuccessful();
});

it('does not send links more than once in the throttle window', function () {
    expectLoginLinkSent(1);

    $this->artisan('instamart:login-link --if-needed')->assertSuccessful();
    $this->artisan('instamart:login-link --if-needed')->expectsOutputToContain('Skipped')->assertSuccessful();
});

it('always sends a link when asked explicitly', function () {
    Connection::factory()->create();
    expectLoginLinkSent(2);

    $this->artisan('instamart:login-link')->assertSuccessful();
    $this->artisan('instamart:login-link')->assertSuccessful();
});

it('needs an owner chat to send to', function () {
    config(['services.swiggy.owner_jid' => null]);
    mock(Wacli::class)->shouldNotReceive('send');

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
    mock(Wacli::class)->shouldNotReceive('send');

    $reply = (string) (new SwiggyLoginTool(chatJid: 'group@g.us', senderJid: LOGIN_OWNER))->handle(new Request([]));

    expect($reply)->toContain('Only the owner');
    Http::assertNothingSent();
});
