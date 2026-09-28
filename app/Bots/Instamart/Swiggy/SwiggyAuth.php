<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Swiggy;

use App\Bots\Instamart\Models\Connection;
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
