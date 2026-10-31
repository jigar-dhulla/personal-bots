<?php

declare(strict_types=1);

use App\Bots\Instamart\Models\Connection;
use App\Bots\Instamart\Swiggy\SwiggyAuth;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use JigarDhulla\LaravelWhatsApp\Services\Wacli;

uses(RefreshDatabase::class);

const WEB_CALLBACK = 'https://bots.example.com/instamart/callback';
const OWNER_JID = '919999999999@s.whatsapp.net';

beforeEach(function () {
    config(['services.swiggy.redirect_uri' => WEB_CALLBACK]);

    Http::fake([
        'mcp.swiggy.com/auth/register' => Http::response(['client_id' => 'web-client']),
        'mcp.swiggy.com/auth/token' => Http::response(['access_token' => 'web-token', 'expires_in' => 432000]),
    ]);
});

/**
 * Start a login as a dashboard user and return the state Swiggy would echo back.
 */
function startWebLogin(): string
{
    $response = test()->actingAs(User::factory()->create())->get(route('instamart.login'));

    $response->assertRedirectContains('https://mcp.swiggy.com/auth/authorize?');
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($query)->toMatchArray([
        'client_id' => 'web-client',
        'redirect_uri' => WEB_CALLBACK,
        'code_challenge_method' => 'S256',
        'scope' => 'mcp:tools',
    ]);

    return $query['state'];
}

it('logs in through the browser and stores the token for the web callback', function () {
    $state = startWebLogin();

    $this->actingAs(User::factory()->create())
        ->get(route('instamart.callback', ['code' => 'auth-code', 'state' => $state]))
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('status', fn (string $status) => str_starts_with($status, 'Logged in to Swiggy.'));

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/auth/token')
        && $request['code'] === 'auth-code'
        && $request['redirect_uri'] === WEB_CALLBACK
        && filled($request['code_verifier']));

    expect(Connection::query()->sole())
        ->client_id->toBe('web-client')
        ->redirect_uri->toBe(WEB_CALLBACK)
        ->access_token->toBe('web-token');
});

it('finishes a login opened from the WhatsApp link without a dashboard session and tells the owner', function () {
    config(['services.swiggy.owner_jid' => OWNER_JID]);
    $wacli = mock(Wacli::class);
    $wacli->shouldReceive('send')->once()->withArgs(fn (string $jid, string $message) => $jid === OWNER_JID && str_contains($message, 'Logged in to Swiggy'))->andReturn([true, '', '']);

    $login = app(SwiggyAuth::class)->beginLogin();
    parse_str((string) parse_url($login->url, PHP_URL_QUERY), $query);

    $this->get(route('instamart.callback', ['code' => 'auth-code', 'state' => $query['state']]))
        ->assertOk()
        ->assertSee('You are logged in');

    expect(Connection::query()->sole()->access_token)->toBe('web-token');
});

it('rejects a callback whose state this app did not issue', function () {
    startWebLogin();

    $this->get(route('instamart.callback', ['code' => 'auth-code', 'state' => 'forged']))
        ->assertOk()
        ->assertSee('Login did not finish')
        ->assertSee('expired or was already used');

    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/auth/token'));
    expect(Connection::query()->count())->toBe(0);
});

it('only lets a state be used once', function () {
    $state = startWebLogin();

    $this->get(route('instamart.callback', ['code' => 'auth-code', 'state' => $state]));
    $this->get(route('instamart.callback', ['code' => 'auth-code', 'state' => $state]))
        ->assertSee('expired or was already used');

    Http::assertSentCount(2);
});

it('reports a login the user cancelled at Swiggy', function () {
    $state = startWebLogin();

    $this->get(route('instamart.callback', ['error' => 'access_denied', 'state' => $state]))
        ->assertSee('Swiggy did not complete the login: access_denied');

    expect(Connection::query()->count())->toBe(0);
});

it('requires a dashboard login only to start a login', function () {
    $this->get(route('instamart.login'))->assertRedirect(route('login'));

    Http::assertNothingSent();
});

it('stays off while the redirect URI is the localhost paste flow', function () {
    config(['services.swiggy.redirect_uri' => 'http://localhost:8765/callback']);

    $this->actingAs(User::factory()->create())->get(route('instamart.login'))
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'instamart:login'));

    Http::assertNothingSent();
});

it('registers a new client when the saved one was made for another redirect URI', function () {
    Connection::factory()->create(['client_id' => 'localhost-client']);

    startWebLogin();

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/auth/register')
        && $request['redirect_uris'] === [WEB_CALLBACK]);
});

it('shows the Swiggy login link in the admin nav only with the web callback', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.dashboard'))->assertSee(route('instamart.login'), false);

    config(['services.swiggy.redirect_uri' => 'http://localhost:8765/callback']);

    $this->actingAs($user)->get(route('admin.dashboard'))->assertDontSee(route('instamart.login'), false);
});

it('points the login command at the dashboard when the web callback is configured', function () {
    $this->artisan('instamart:login')
        ->expectsOutputToContain(route('instamart.login'))
        ->assertFailed();

    Http::assertNothingSent();
});
