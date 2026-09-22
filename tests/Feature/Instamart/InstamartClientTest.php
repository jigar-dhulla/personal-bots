<?php

declare(strict_types=1);

use App\Bots\Instamart\Models\Connection;
use App\Bots\Instamart\Swiggy\InstamartClient;
use App\Bots\Instamart\Swiggy\SwiggyException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

uses(RefreshDatabase::class);

beforeEach(function () {
    Sleep::fake();
});

it('refuses to call Swiggy without an active login', function () {
    Connection::factory()->expired()->create();
    Http::fake();

    expect(fn () => app(InstamartClient::class)->call('get_cart'))
        ->toThrow(fn (SwiggyException $e) => expect($e->needsLogin())->toBeTrue());

    Http::assertNothingSent();
});

it('initialises one MCP session and reuses it with the bearer token', function () {
    $connection = Connection::factory()->create(['access_token' => 'secret-token']);
    fakeInstamart(['get_cart' => ['items' => []]]);

    $client = app(InstamartClient::class);
    $client->call('get_cart');
    $client->call('get_cart');

    $initialises = Http::recorded()->filter(fn (array $pair) => ($pair[0]->data()['method'] ?? null) === 'initialize');

    expect($initialises)->toHaveCount(1);

    Http::assertSent(fn (Request $request) => ($request->data()['method'] ?? null) === 'tools/call'
        && $request->hasHeader('Authorization', 'Bearer secret-token')
        && $request->hasHeader('Mcp-Session-Id', 'session-1')
        && $request->url() === 'https://mcp.swiggy.com/im');
});

it('unwraps the data from a server-sent event stream', function () {
    Connection::factory()->create();

    Http::fake(function (Request $request) {
        if ($request->data()['method'] !== 'tools/call') {
            return Http::response('', 202);
        }

        $envelope = json_encode(['success' => true, 'data' => ['cartTotalAmount' => '120']]);
        $message = json_encode(['jsonrpc' => '2.0', 'id' => $request->data()['id'], 'result' => ['content' => [['type' => 'text', 'text' => $envelope]]]]);

        return Http::response("event: message\ndata: {$message}\n\n", 200, ['Content-Type' => 'text/event-stream']);
    });

    expect(app(InstamartClient::class)->call('get_cart'))->toBe(['cartTotalAmount' => '120']);
});

it('surfaces a success:false envelope as a domain failure', function () {
    Connection::factory()->create();
    fakeInstamart(['update_cart' => fn () => Http::response(['jsonrpc' => '2.0', 'id' => '1', 'result' => [
        'isError' => true,
        'content' => [['type' => 'text', 'text' => json_encode(['success' => false, 'error' => ['message' => 'Item out of stock']])]],
    ]])]);

    expect(fn () => app(InstamartClient::class)->call('update_cart', ['items' => []]))
        ->toThrow(SwiggyException::class, 'Item out of stock');
});

it('expires the saved login when Swiggy rejects the token', function () {
    $connection = Connection::factory()->create();
    fakeInstamart(['get_cart' => fn () => Http::response('', 401)]);

    expect(fn () => app(InstamartClient::class)->call('get_cart'))
        ->toThrow(fn (SwiggyException $e) => expect($e->needsLogin())->toBeTrue());

    expect($connection->fresh()->isExpired())->toBeTrue();
});

it('retries a transient upstream failure for safe tools', function () {
    Connection::factory()->create();
    $attempts = 0;
    fakeInstamart(['get_cart' => function () use (&$attempts) {
        return ++$attempts < 3 ? Http::response('', 503) : ['items' => []];
    }]);

    expect(app(InstamartClient::class)->call('get_cart'))->toBe(['items' => []])
        ->and($attempts)->toBe(3);
});

it('never retries a call marked unsafe to repeat', function () {
    Connection::factory()->create();
    fakeInstamart(['checkout' => fn () => Http::response('', 503)]);

    expect(fn () => app(InstamartClient::class)->call('checkout', [], retryable: false))
        ->toThrow(fn (SwiggyException $e) => expect($e->isTransient())->toBeTrue());

    expect(instamartCalls('checkout'))->toHaveCount(1);
});

it('reads data the live server puts straight into structuredContent', function () {
    Connection::factory()->create();
    Http::fake(fn (Request $request) => $request->data()['method'] === 'tools/call'
        ? Http::response(['id' => $request->data()['id'], 'result' => [
            'content' => [['type' => 'text', 'text' => 'Found 1 saved address.']],
            'structuredContent' => ['addresses' => [['id' => 'a1', 'addressLine' => '12 MG Road']]],
        ]])
        : Http::response(['result' => []]));

    expect(app(InstamartClient::class)->call('get_addresses'))
        ->toBe(['addresses' => [['id' => 'a1', 'addressLine' => '12 MG Road']]]);
});

it('treats an error result without an envelope as a domain failure', function () {
    Connection::factory()->create();
    Http::fake(fn (Request $request) => Http::response(['id' => $request->data()['id'] ?? '1', 'result' => [
        'isError' => true,
        'content' => [['type' => 'text', 'text' => 'Invalid addressId']],
    ]]));

    expect(fn () => app(InstamartClient::class)->call('search_products', ['addressId' => 'x', 'query' => 'milk']))
        ->toThrow(SwiggyException::class, 'Invalid addressId');
});
