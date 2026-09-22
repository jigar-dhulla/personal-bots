<?php

declare(strict_types=1);

use App\Bots\Instamart\Models\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function fakeSwiggyOAuth(): void
{
    Http::fake([
        'mcp.swiggy.com/auth/register' => Http::response(['client_id' => 'client-123']),
        'mcp.swiggy.com/auth/token' => Http::response(['access_token' => 'token-abc', 'token_type' => 'Bearer', 'expires_in' => 432000]),
    ]);
}

it('registers a client, exchanges the code and stores an encrypted token', function () {
    Str::createRandomStringsUsing(fn () => 'fixed-state');
    fakeSwiggyOAuth();

    $this->artisan('instamart:login')
        ->expectsOutputToContain('https://mcp.swiggy.com/auth/authorize?')
        ->expectsQuestion('Redirected URL', 'http://localhost:8765/callback?code=auth-code&state=fixed-state')
        ->expectsOutputToContain('Logged in to Swiggy')
        ->assertSuccessful();

    Str::createRandomStringsNormally();

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/auth/token')
        && $request['grant_type'] === 'authorization_code'
        && $request['code'] === 'auth-code'
        && $request['client_id'] === 'client-123'
        && $request['redirect_uri'] === 'http://localhost:8765/callback'
        && filled($request['code_verifier']));

    $connection = Connection::query()->sole();
    expect($connection->client_id)->toBe('client-123')
        ->and($connection->access_token)->toBe('token-abc')
        ->and($connection->getRawOriginal('access_token'))->not->toBe('token-abc')
        ->and($connection->expires_at->isFuture())->toBeTrue();
});

it('reuses the registered client on the next login', function () {
    Connection::factory()->expired()->create(['client_id' => 'client-old']);
    Str::createRandomStringsUsing(fn () => 'fixed-state');
    fakeSwiggyOAuth();

    $this->artisan('instamart:login')
        ->expectsQuestion('Redirected URL', 'http://localhost:8765/callback?code=auth-code&state=fixed-state')
        ->assertSuccessful();

    Str::createRandomStringsNormally();

    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/auth/register'));
    expect(Connection::query()->sole())->client_id->toBe('client-old')->isExpired()->toBeFalse();
});

it('rejects a redirect that belongs to another login attempt', function () {
    fakeSwiggyOAuth();

    $this->artisan('instamart:login')
        ->expectsQuestion('Redirected URL', 'http://localhost:8765/callback?code=abc&state=forged')
        ->expectsOutputToContain('state mismatch')
        ->assertFailed();

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/auth/token'));
    expect(Connection::query()->count())->toBe(0);
});

it('reports the saved login status', function () {
    Connection::factory()->create();

    $this->artisan('instamart:login --status')->expectsOutputToContain('Logged in.')->assertSuccessful();
});

it('reports an expired login', function () {
    Connection::factory()->expired()->create();

    $this->artisan('instamart:login --status')->expectsOutputToContain('expired')->assertFailed();
});
