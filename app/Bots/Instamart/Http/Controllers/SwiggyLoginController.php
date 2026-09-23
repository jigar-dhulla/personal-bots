<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Http\Controllers;

use App\Bots\Instamart\Models\Connection;
use App\Bots\Instamart\Swiggy\SwiggyAuth;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Logs the Instamart bot into Swiggy from the browser: the dashboard starts
 * the OAuth + PKCE flow and Swiggy sends the browser back to
 * `/instamart/callback`, which finishes it. Both steps need a dashboard
 * login, and the callback only accepts the state this session started, so a
 * stray or forged callback can never replace the saved token.
 *
 * Needs an HTTPS `SWIGGY_REDIRECT_URI` pointing at the callback and
 * allowlisted by Swiggy. With the default localhost redirect, use
 * `php artisan instamart:login` instead.
 */
class SwiggyLoginController extends Controller
{
    private const string SESSION_KEY = 'instamart.oauth';

    public function start(Request $request, SwiggyAuth $auth): RedirectResponse
    {
        if (! SwiggyAuth::usesWebCallback()) {
            return $this->backToDashboard('Web login needs an HTTPS SWIGGY_REDIRECT_URI. Run `php artisan instamart:login` instead.');
        }

        $redirectUri = SwiggyAuth::redirectUri();

        try {
            $clientId = $auth->clientIdFor($redirectUri);
        } catch (RuntimeException $exception) {
            return $this->backToDashboard($exception->getMessage());
        }

        $pkce = SwiggyAuth::pkcePair();
        $state = SwiggyAuth::state();

        $request->session()->put(self::SESSION_KEY, [
            'state' => $state,
            'verifier' => $pkce['verifier'],
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
        ]);

        return redirect()->away($auth->authorizationUrl($clientId, $redirectUri, $pkce['challenge'], $state));
    }

    public function callback(Request $request, SwiggyAuth $auth): RedirectResponse
    {
        /** @var array{state: string, verifier: string, client_id: string, redirect_uri: string}|null $pending */
        $pending = $request->session()->pull(self::SESSION_KEY);

        if ($pending === null || ! hash_equals($pending['state'], (string) $request->query('state'))) {
            return $this->backToDashboard('That Swiggy login did not start from this dashboard session. Start it again from "Swiggy login".');
        }

        if (filled($request->query('error'))) {
            return $this->backToDashboard('Swiggy did not complete the login: '.$request->query('error_description', $request->query('error')));
        }

        if (blank($request->query('code'))) {
            return $this->backToDashboard('Swiggy sent no authorization code. Start the login again.');
        }

        try {
            $token = $auth->exchange($pending['client_id'], (string) $request->query('code'), $pending['verifier'], $pending['redirect_uri']);
        } catch (RuntimeException $exception) {
            return $this->backToDashboard($exception->getMessage());
        }

        $connection = Connection::store($pending['client_id'], $pending['redirect_uri'], $token['access_token'], $token['expires_in']);

        return $this->backToDashboard(sprintf(
            'Logged in to Swiggy. The token expires %s (%s).',
            $connection->expires_at->toDayDateTimeString(),
            $connection->expires_at->diffForHumans(),
        ));
    }

    private function backToDashboard(string $status): RedirectResponse
    {
        return redirect()->route('admin.dashboard')->with('status', $status);
    }
}
