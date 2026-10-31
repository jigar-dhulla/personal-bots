<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Http\Controllers;

use App\Bots\Instamart\Swiggy\LoginLink;
use App\Bots\Instamart\Swiggy\SwiggyAuth;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use JigarDhulla\SwiggyMcp\Exceptions\OAuthException;

/**
 * Logs the Instamart bot into Swiggy from the browser: the dashboard (or the
 * link the bot sends on WhatsApp) starts the OAuth + PKCE flow and Swiggy
 * sends the browser back to `/instamart/callback`, which finishes it. The
 * callback only accepts a one-time state this app issued, so a stray or
 * forged callback can never replace the saved token.
 *
 * Needs an HTTPS `SWIGGY_REDIRECT_URI` pointing at the callback and
 * allowlisted by Swiggy. With the default localhost redirect, use
 * `php artisan instamart:login` instead.
 */
class SwiggyLoginController extends Controller
{
    public function start(SwiggyAuth $auth): RedirectResponse
    {
        if (! SwiggyAuth::usesWebCallback()) {
            return $this->backToDashboard('Web login needs an HTTPS SWIGGY_REDIRECT_URI. Run `php artisan instamart:login` instead.');
        }

        try {
            $login = $auth->beginLogin();
        } catch (OAuthException $exception) {
            return $this->backToDashboard($exception->getMessage());
        }

        return redirect()->away($login->url);
    }

    /**
     * Finishes a login started from the dashboard or from the WhatsApp link,
     * so it needs no dashboard session: the one-time state in the URL is the
     * proof, and the owner is told on WhatsApp either way.
     */
    public function callback(Request $request, SwiggyAuth $auth, LoginLink $link): RedirectResponse|View
    {
        try {
            $connection = $auth->completeLogin($request->fullUrl());
        } catch (OAuthException $exception) {
            return $this->finish($request, $exception->getMessage(), succeeded: false);
        }

        $link->announceLoggedIn($connection);

        return $this->finish($request, sprintf(
            'Logged in to Swiggy. The token expires %s (%s).',
            $connection->expires_at->toDayDateTimeString(),
            $connection->expires_at->diffForHumans(),
        ), succeeded: true);
    }

    private function finish(Request $request, string $status, bool $succeeded): RedirectResponse|View
    {
        return $request->user() !== null
            ? $this->backToDashboard($status)
            : view('instamart.login-result', ['status' => $status, 'succeeded' => $succeeded]);
    }

    private function backToDashboard(string $status): RedirectResponse
    {
        return redirect()->route('admin.dashboard')->with('status', $status);
    }
}
