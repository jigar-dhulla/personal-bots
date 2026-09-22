<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Swiggy;

use RuntimeException;

/**
 * A failed Swiggy MCP call, classified the way Swiggy's error guide buckets
 * them so callers know whether to retry, re-login, or just tell the user.
 *
 * @see https://mcp.swiggy.com/builders/docs/reference/errors
 */
class SwiggyException extends RuntimeException
{
    public const string NOT_CONNECTED = 'not_connected';

    public const string UNAUTHENTICATED = 'unauthenticated';

    public const string RATE_LIMITED = 'rate_limited';

    public const string TRANSIENT = 'transient';

    public const string DOMAIN = 'domain';

    public const string INVALID = 'invalid';

    public function __construct(public readonly string $kind, string $message)
    {
        parent::__construct($message);
    }

    public static function notConnected(): self
    {
        return new self(self::NOT_CONNECTED, 'No active Swiggy login.');
    }

    public static function unauthenticated(): self
    {
        return new self(self::UNAUTHENTICATED, 'Swiggy rejected the access token.');
    }

    public static function rateLimited(?int $retryAfterSeconds): self
    {
        return new self(self::RATE_LIMITED, sprintf('Swiggy rate limit hit; retry after %d seconds.', $retryAfterSeconds ?? 60));
    }

    public static function transient(string $message): self
    {
        return new self(self::TRANSIENT, $message);
    }

    /**
     * A business failure Swiggy reported with `success: false` — out of
     * stock, unserviceable address, minimum not met. Not worth retrying.
     */
    public static function domain(string $message): self
    {
        return new self(self::DOMAIN, $message);
    }

    public static function invalid(string $message): self
    {
        return new self(self::INVALID, $message);
    }

    public function isTransient(): bool
    {
        return $this->kind === self::TRANSIENT;
    }

    public function needsLogin(): bool
    {
        return in_array($this->kind, [self::NOT_CONNECTED, self::UNAUTHENTICATED], true);
    }
}
