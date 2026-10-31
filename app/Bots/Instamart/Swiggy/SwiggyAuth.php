<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Swiggy;

use App\Bots\Instamart\Models\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use JigarDhulla\SwiggyMcp\Auth\AccessToken;
use JigarDhulla\SwiggyMcp\Auth\AuthorizationRequest;
use JigarDhulla\SwiggyMcp\Auth\OAuth;
use JigarDhulla\SwiggyMcp\Exceptions\OAuthException;

/**
 * Swiggy's OAuth 2.1 + PKCE flow (via the `jigar-dhulla/swiggy-mcp`
 * package) tied to this app's config and saved {@see Connection}. Swiggy
 * issues no refresh tokens, so a login lasts until the token expires.
 *
 * @see https://mcp.swiggy.com/builders/docs/start/authenticate
 */
class SwiggyAuth
{
    /** How long a started login stays valid for its callback. */
    private const int PENDING_MINUTES = 15;

    /**
     * The OAuth client to log in with. Swiggy ties a client to the redirect
     * URIs it registered with, so the saved one is reused only when it was
     * registered for this redirect; otherwise a new client is registered.
     *
     * @throws OAuthException
     */
    public function clientIdFor(string $redirectUri): string
    {
        $current = Connection::current();

        return $current !== null && $current->redirect_uri === $redirectUri
            ? $current->client_id
            : $this->oauth($redirectUri)->register();
    }

    /**
     * Start a login: the URL to send the owner to, plus the PKCE verifier and
     * state needed to finish it.
     */
    public function authorize(string $clientId, string $redirectUri): AuthorizationRequest
    {
        return $this->oauth($redirectUri)->authorize($clientId, state: Str::random(40));
    }

    /**
     * Finish a login from the URL Swiggy redirected to.
     *
     * @throws OAuthException
     */
    public function exchange(AuthorizationRequest $request, string $callbackUrl): AccessToken
    {
        return $this->oauth($request->redirectUri)->exchange($request, $callbackUrl);
    }

    /**
     * Start a login that finishes later, from a callback or a pasted URL
     * rather than the same process. The PKCE verifier waits in the cache,
     * keyed by the one-time state, so no browser session is needed and the
     * link can be opened on any device.
     *
     * @throws OAuthException
     */
    public function beginLogin(): AuthorizationRequest
    {
        $redirectUri = self::redirectUri();
        $login = $this->authorize($this->clientIdFor($redirectUri), $redirectUri);

        Cache::put(self::pendingKey($login->state), $login->toArray(), now()->addMinutes(self::PENDING_MINUTES));

        return $login;
    }

    /**
     * Finish a login begun by {@see beginLogin()} from the URL Swiggy
     * redirected to. Only a state this app issued, and only once, gets
     * through, so a forged or replayed callback cannot replace the token.
     *
     * @throws OAuthException
     */
    public function completeLogin(string $callbackUrl): Connection
    {
        parse_str((string) parse_url($callbackUrl, PHP_URL_QUERY), $query);

        $state = (string) ($query['state'] ?? '');
        $pending = $state === '' ? null : Cache::pull(self::pendingKey($state));

        if (! is_array($pending)) {
            throw new OAuthException('That Swiggy login link has expired or was already used. Ask for a new one.');
        }

        $login = AuthorizationRequest::fromArray($pending);
        $token = $this->exchange($login, $callbackUrl);

        return Connection::store($login->clientId, $login->redirectUri, $token->value, $token->expiresIn);
    }

    /**
     * Revoke the session on Swiggy's side. Best effort: an already-expired
     * token is fine to drop locally regardless.
     */
    public function logout(Connection $connection): bool
    {
        return $this->oauth($connection->redirect_uri)->logout($connection->access_token);
    }

    /**
     * The redirect URI Swiggy sends the browser back to after login.
     */
    public static function redirectUri(): string
    {
        return (string) config('services.swiggy.redirect_uri');
    }

    /**
     * Whether logins can finish in the browser at `/instamart/callback`.
     * Only an HTTPS redirect can reach this app; Swiggy allows plain
     * http://localhost, which the `instamart:login` paste flow uses.
     */
    public static function usesWebCallback(): bool
    {
        return str_starts_with(self::redirectUri(), 'https://');
    }

    private static function pendingKey(string $state): string
    {
        return 'instamart:oauth:'.hash('sha256', $state);
    }

    private function oauth(string $redirectUri): OAuth
    {
        return new OAuth(
            $redirectUri,
            (string) config('app.name'),
            httpClient: new LaravelHttpClient(30),
            baseUrl: (string) config('services.swiggy.auth_url'),
        );
    }
}
