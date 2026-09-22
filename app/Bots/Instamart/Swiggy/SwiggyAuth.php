<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Swiggy;

use App\Bots\Instamart\Models\Connection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Swiggy's OAuth 2.1 + PKCE endpoints: dynamic client registration, the
 * browser authorize step, the code-for-token exchange, and logout. Swiggy
 * issues no refresh tokens, so a login lasts until the token expires.
 *
 * @see https://mcp.swiggy.com/builders/docs/start/authenticate
 */
class SwiggyAuth
{
    /**
     * Register this app as an OAuth client (RFC 7591) and return its id.
     *
     * @throws RuntimeException
     */
    public function register(string $redirectUri): string
    {
        $response = $this->request()->post($this->url('/auth/register'), [
            'client_name' => (string) config('app.name'),
            'redirect_uris' => [$redirectUri],
            'grant_types' => ['authorization_code'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ]);

        $clientId = $response->json('client_id');

        if (! $response->successful() || ! is_string($clientId) || $clientId === '') {
            throw new RuntimeException('Swiggy client registration failed: '.$response->body());
        }

        return $clientId;
    }

    public function authorizationUrl(string $clientId, string $redirectUri, string $codeChallenge, string $state): string
    {
        return $this->url('/auth/authorize').'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'state' => $state,
            'scope' => 'mcp:tools',
        ]);
    }

    /**
     * Exchange an authorization code for an access token.
     *
     * @return array{access_token: string, expires_in: int}
     *
     * @throws RuntimeException
     */
    public function exchange(string $clientId, string $code, string $codeVerifier, string $redirectUri): array
    {
        $response = $this->request()->post($this->url('/auth/token'), [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'code_verifier' => $codeVerifier,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
        ]);

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new RuntimeException('Swiggy token exchange failed: '.$response->body());
        }

        return [
            'access_token' => $token,
            'expires_in' => (int) ($response->json('expires_in') ?? 0),
        ];
    }

    /**
     * Revoke the session on Swiggy's side. Best effort: an already-expired
     * token is fine to drop locally regardless.
     */
    public function logout(Connection $connection): bool
    {
        try {
            return $this->request()->withToken($connection->access_token)->post($this->url('/auth/logout'))->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    /**
     * A random PKCE verifier and its S256 challenge.
     *
     * @return array{verifier: string, challenge: string}
     */
    public static function pkcePair(): array
    {
        $verifier = self::base64Url(random_bytes(32));

        return [
            'verifier' => $verifier,
            'challenge' => self::base64Url(hash('sha256', $verifier, true)),
        ];
    }

    public static function state(): string
    {
        return Str::random(40);
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()->asJson()->timeout(30);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.swiggy.auth_url'), '/').$path;
    }
}
