<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Swiggy;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * A PSR-18 client for the Swiggy MCP package that sends through Laravel's
 * HTTP client, so requests get its timeout and `Http::fake()` in tests.
 */
class LaravelHttpClient implements ClientInterface
{
    public function __construct(private int $timeoutSeconds = 60) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $headers = [];

        foreach ($request->getHeaders() as $name => $values) {
            if (! in_array(strtolower($name), ['host', 'content-type'], true)) {
                $headers[$name] = implode(', ', $values);
            }
        }

        try {
            return Http::withHeaders($headers)
                ->withBody((string) $request->getBody(), $request->getHeaderLine('Content-Type') ?: 'application/json')
                ->timeout($this->timeoutSeconds)
                ->send($request->getMethod(), (string) $request->getUri())
                ->toPsrResponse();
        } catch (ConnectionException $exception) {
            throw new class($exception->getMessage(), $request, $exception) extends RuntimeException implements NetworkExceptionInterface
            {
                public function __construct(string $message, private RequestInterface $request, ConnectionException $previous)
                {
                    parent::__construct($message, previous: $previous);
                }

                public function getRequest(): RequestInterface
                {
                    return $this->request;
                }
            };
        }
    }
}
