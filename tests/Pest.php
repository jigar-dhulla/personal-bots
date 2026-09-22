<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Point `whatsapp-agent.agents` at a single entry for the given agent class,
 * so instruction tests can control the triggers an agent sees.
 *
 * @param  array<string, mixed>  $overrides
 */
function setAgentConfig(string $agent, array $overrides = []): void
{
    config(['whatsapp-agent.agents' => [
        array_merge([
            'agent' => $agent,
            'triggers' => [],
            'chats' => [],
            'groups' => [],
        ], $overrides),
    ]]);
}

/**
 * Fake Swiggy's Instamart MCP server. `$tools` maps a tool name to the
 * `data` it returns, or to a closure receiving the call's arguments and
 * returning either `data` or a full HTTP response (for failures). Any tool
 * not listed answers with an empty success envelope.
 *
 * @param  array<string, array<string, mixed>|Closure>  $tools
 */
function fakeInstamart(array $tools = []): void
{
    Http::fake(function (Request $request) use ($tools) {
        $payload = $request->data();
        $method = $payload['method'] ?? null;

        if ($method === 'initialize') {
            return Http::response(['jsonrpc' => '2.0', 'id' => $payload['id'], 'result' => ['protocolVersion' => '2025-06-18']], 200, ['Mcp-Session-Id' => 'session-1']);
        }

        if ($method !== 'tools/call') {
            return Http::response('', 202);
        }

        $tool = $payload['params']['name'];
        $handler = $tools[$tool] ?? [];
        $data = $handler instanceof Closure ? $handler((array) $payload['params']['arguments']) : $handler;

        if (! is_array($data)) {
            return $data;
        }

        return Http::response(['jsonrpc' => '2.0', 'id' => $payload['id'], 'result' => [
            'content' => [['type' => 'text', 'text' => json_encode(['success' => true, 'data' => $data])]],
        ]]);
    });
}

/**
 * The arguments of every `tools/call` sent to the faked Instamart server for
 * the given tool, in order.
 *
 * @return array<int, array<string, mixed>>
 */
function instamartCalls(string $tool): array
{
    return Http::recorded()
        ->map(fn (array $pair) => $pair[0]->data())
        ->filter(fn (array $payload) => ($payload['method'] ?? null) === 'tools/call' && $payload['params']['name'] === $tool)
        ->map(fn (array $payload) => (array) $payload['params']['arguments'])
        ->values()
        ->all();
}
