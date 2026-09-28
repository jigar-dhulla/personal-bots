<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Swiggy;

use App\Bots\Instamart\Models\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use JigarDhulla\SwiggyMcp\Exceptions\AuthenticationException;
use JigarDhulla\SwiggyMcp\Exceptions\SwiggyException;
use JigarDhulla\SwiggyMcp\McpClient;
use JigarDhulla\SwiggyMcp\RetryPolicy;
use JigarDhulla\SwiggyMcp\Session\CacheSessionStore;
use JigarDhulla\SwiggyMcp\Swiggy;

/**
 * The owner's Instamart MCP client: the `jigar-dhulla/swiggy-mcp` package
 * wired to the saved {@see Connection}, Laravel's HTTP client, cache
 * (so MCP sessions survive between queue jobs) and log.
 *
 * A token Swiggy rejects is marked expired so the dashboard and
 * `instamart:login --status` show that a new login is needed.
 */
class InstamartClient
{
    /**
     * Call an Instamart tool and return its data.
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
        $connection = Connection::active() ?? throw new AuthenticationException('No active Swiggy login.');

        try {
            return $this->mcp($connection)->call($tool, $arguments, $retryable);
        } catch (AuthenticationException $exception) {
            $connection->expire();

            throw $exception;
        }
    }

    /**
     * Drop the cached MCP session, e.g. after logging out.
     */
    public function forgetSession(Connection $connection): void
    {
        $this->mcp($connection)->forgetSession();
    }

    private function mcp(Connection $connection): McpClient
    {
        $swiggy = new Swiggy(
            $connection->access_token,
            httpClient: new LaravelHttpClient,
            sessions: new CacheSessionStore(Cache::store()),
            retry: new RetryPolicy(sleep: fn (int $milliseconds) => Sleep::for($milliseconds)->milliseconds()),
            logger: logger(),
            clientName: Str::slug((string) config('app.name')),
        );

        return $swiggy->endpoint((string) config('services.swiggy.instamart_url'));
    }
}
