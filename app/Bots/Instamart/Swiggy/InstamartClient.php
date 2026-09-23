<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Swiggy;

use App\Bots\Instamart\Models\Connection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/**
 * A minimal MCP client for Swiggy's Instamart server: JSON-RPC 2.0 over
 * streamable HTTP, authenticated with the owner's bearer token.
 *
 * It keeps one MCP session per login (Swiggy counts every `initialize` as an
 * auth event, so sessions are reused), retries transient upstream failures
 * with backoff for tools that are safe to repeat, and unwraps Swiggy's
 * `{success, data, message}` envelope.
 *
 * @see https://mcp.swiggy.com/builders/docs/start/developer/build-an-agent
 */
class InstamartClient
{
    private const string PROTOCOL_VERSION = '2025-06-18';

    /**
     * Pause before each retry of a transient failure, in milliseconds.
     * Swiggy suggests starting at 500ms and doubling.
     *
     * @var array<int, int>
     */
    private const array RETRY_DELAYS_MS = [500, 1000, 2000];

    /**
     * Call an Instamart tool and return its `data` payload. The envelope's
     * top-level `message`, when present, is merged in under `message`.
     *
     * Pass `$retryable = false` for tools that must never be repeated blindly
     * (`checkout`): a transient failure is then surfaced immediately.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     *
     * @throws SwiggyException
     */
    public function call(string $tool, array $arguments = [], bool $retryable = true): array
    {
        $connection = Connection::active() ?? throw SwiggyException::notConnected();

        $attempt = 0;

        while (true) {
            try {
                return $this->callOnce($connection, $tool, $arguments);
            } catch (SwiggyException $exception) {
                if (! $retryable || ! $exception->isTransient() || $attempt >= count(self::RETRY_DELAYS_MS)) {
                    Log::warning('swiggy.mcp.failed', [
                        'tool' => $tool,
                        'kind' => $exception->kind,
                        'message' => $exception->getMessage(),
                        'attempts' => $attempt + 1,
                    ]);

                    throw $exception;
                }

                Sleep::for(self::RETRY_DELAYS_MS[$attempt++])->milliseconds();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function callOnce(Connection $connection, string $tool, array $arguments, bool $freshSession = false): array
    {
        $sessionId = $this->session($connection);

        $response = $this->post($connection, $sessionId, [
            'jsonrpc' => '2.0',
            'id' => (string) Str::uuid(),
            'method' => 'tools/call',
            'params' => ['name' => $tool, 'arguments' => (object) $arguments],
        ]);

        // MCP servers answer 404 once a session has expired; start a new one.
        if ($response->status() === 404 && $sessionId !== '' && ! $freshSession) {
            $this->forgetSession($connection);

            return $this->callOnce($connection, $tool, $arguments, freshSession: true);
        }

        $message = $this->rpcMessage($connection, $response);

        $this->reportDeprecation($tool, $message);

        return $this->unwrap($message);
    }

    /**
     * Swiggy announces tool and parameter deprecations in
     * `_meta.swiggy.deprecation`; surface them in the logs well before the
     * removal date.
     *
     * @param  array<string, mixed>  $message
     */
    private function reportDeprecation(string $tool, array $message): void
    {
        $deprecation = $message['result']['_meta']['swiggy']['deprecation']
            ?? $message['_meta']['swiggy']['deprecation']
            ?? null;

        if (filled($deprecation)) {
            Log::warning('swiggy.mcp.deprecation', ['tool' => $tool, 'deprecation' => $deprecation]);
        }
    }

    /**
     * The MCP session id for this login, initialising one when needed. An
     * empty string means the server runs stateless and issued none.
     */
    private function session(Connection $connection): string
    {
        return Cache::remember($this->sessionCacheKey($connection), now()->addHours(12), function () use ($connection): string {
            $response = $this->post($connection, '', [
                'jsonrpc' => '2.0',
                'id' => (string) Str::uuid(),
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => self::PROTOCOL_VERSION,
                    'capabilities' => (object) [],
                    'clientInfo' => ['name' => Str::slug((string) config('app.name')), 'version' => '1.0'],
                ],
            ]);

            $this->rpcMessage($connection, $response);

            $sessionId = (string) $response->header('Mcp-Session-Id');

            $this->post($connection, $sessionId, ['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);

            return $sessionId;
        });
    }

    /**
     * Drop the cached MCP session, e.g. after logging out.
     */
    public function forgetSession(Connection $connection): void
    {
        Cache::forget($this->sessionCacheKey($connection));
    }

    private function sessionCacheKey(Connection $connection): string
    {
        return 'instamart:mcp-session:'.$connection->getKey();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(Connection $connection, string $sessionId, array $payload): Response
    {
        $headers = ['MCP-Protocol-Version' => self::PROTOCOL_VERSION];

        if ($sessionId !== '') {
            $headers['Mcp-Session-Id'] = $sessionId;
        }

        $startedAt = hrtime(true);

        try {
            $response = Http::withToken($connection->access_token)
                ->accept('application/json, text/event-stream')
                ->withHeaders($headers)
                ->timeout(60)
                ->post((string) config('services.swiggy.instamart_url'), $payload);
        } catch (ConnectionException $exception) {
            Log::warning('swiggy.mcp.call', $this->callContext($payload, $sessionId, $startedAt) + [
                'status' => null,
                'error' => $exception->getMessage(),
            ]);

            throw SwiggyException::transient($exception->getMessage());
        }

        $context = $this->callContext($payload, $sessionId, $startedAt) + [
            'status' => $response->status(),
            'response_session_id' => $response->header('Mcp-Session-Id') ?: null,
            'rate_limit_remaining' => $response->header('X-RateLimit-Remaining') ?: null,
        ];

        $response->successful()
            ? Log::info('swiggy.mcp.call', $context)
            : Log::warning('swiggy.mcp.call', $context + ['body' => Str::limit($response->body(), 500)]);

        return $response;
    }

    /**
     * What Swiggy asks for when a call is escalated: which tool, when, which
     * session and request id, and with what arguments. Never the token.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function callContext(array $payload, string $sessionId, int|float $startedAt): array
    {
        return [
            'method' => $payload['method'] ?? null,
            'tool' => $payload['params']['name'] ?? null,
            'request_id' => $payload['id'] ?? null,
            'session_id' => $sessionId !== '' ? $sessionId : null,
            'arguments' => (array) ($payload['params']['arguments'] ?? []),
            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            'at' => now()->toIso8601String(),
        ];
    }

    /**
     * Turn an HTTP response into the JSON-RPC message it carries, mapping
     * transport and protocol failures onto {@see SwiggyException} kinds.
     *
     * @return array<string, mixed>
     */
    private function rpcMessage(Connection $connection, Response $response): array
    {
        if (in_array($response->status(), [401, 419], true)) {
            $connection->expire();

            throw SwiggyException::unauthenticated();
        }

        if ($response->status() === 429) {
            throw SwiggyException::rateLimited($response->header('Retry-After') !== '' ? (int) $response->header('Retry-After') : null);
        }

        $message = $this->decodeBody($response);

        if ($response->serverError()) {
            throw SwiggyException::transient($this->errorText($message) ?? 'Swiggy returned HTTP '.$response->status().'.');
        }

        if ($response->failed()) {
            throw SwiggyException::invalid($this->errorText($message) ?? 'Swiggy returned HTTP '.$response->status().'.');
        }

        if (isset($message['error'])) {
            if ((int) ($message['error']['code'] ?? 0) === -32001) {
                $connection->expire();

                throw SwiggyException::unauthenticated();
            }

            $text = (string) ($message['error']['message'] ?? 'Swiggy MCP error.');

            // -32603 is Swiggy's internal error (worth a retry); the rest are bad requests.
            throw (int) ($message['error']['code'] ?? 0) === -32603
                ? SwiggyException::transient($text)
                : SwiggyException::invalid($text);
        }

        return $message;
    }

    /**
     * Streamable HTTP may answer with plain JSON or a one-shot SSE stream
     * whose `data:` lines carry the JSON-RPC message.
     *
     * @return array<string, mixed>
     */
    private function decodeBody(Response $response): array
    {
        $body = $response->body();

        if (str_contains((string) $response->header('Content-Type'), 'text/event-stream')) {
            $messages = collect(preg_split('/\r?\n/', $body))
                ->filter(fn (string $line): bool => str_starts_with($line, 'data:'))
                ->map(fn (string $line): mixed => json_decode(trim(substr($line, 5)), true))
                ->filter(fn (mixed $message): bool => is_array($message) && (isset($message['result']) || isset($message['error'])));

            return $messages->last() ?? [];
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function errorText(array $message): ?string
    {
        $text = $message['error']['message'] ?? $message['message'] ?? null;

        return is_string($text) && $text !== '' ? $text : null;
    }

    /**
     * Unwrap a `tools/call` result into the tool's data.
     *
     * The docs describe a `{success, data, message}` envelope, but the live
     * server puts the data straight into `structuredContent` (with a prose
     * summary in `content`). Both shapes are accepted: anything carrying a
     * `success` key is treated as the envelope, anything else as the data.
     *
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    private function unwrap(array $message): array
    {
        $result = (array) ($message['result'] ?? []);
        $isError = ($result['isError'] ?? false) === true;

        $text = collect($result['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");

        $payload = $result['structuredContent'] ?? null;

        if (! is_array($payload)) {
            $decoded = json_decode($text, true);
            $payload = is_array($decoded) ? $decoded : null;
        }

        if ($payload === null) {
            if ($isError) {
                throw SwiggyException::domain($text !== '' ? $text : 'Swiggy could not complete that request.');
            }

            return $text !== '' ? ['message' => $text] : [];
        }

        if (! array_key_exists('success', $payload)) {
            if ($isError) {
                throw SwiggyException::domain((string) ($payload['error']['message'] ?? $payload['message'] ?? ($text !== '' ? $text : 'Swiggy could not complete that request.')));
            }

            return $payload;
        }

        if ($payload['success'] === false || $isError) {
            throw SwiggyException::domain((string) ($payload['error']['message'] ?? $payload['message'] ?? 'Swiggy could not complete that request.'));
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        if (isset($payload['message']) && is_string($payload['message']) && ! isset($data['message'])) {
            $data['message'] = $payload['message'];
        }

        return $data;
    }
}
