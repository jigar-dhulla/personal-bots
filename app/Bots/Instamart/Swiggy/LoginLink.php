<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Swiggy;

use App\Bots\Instamart\Models\Connection;
use Illuminate\Support\Facades\Cache;
use JigarDhulla\LaravelWhatsApp\Services\Wacli;
use JigarDhulla\SwiggyMcp\Exceptions\OAuthException;

/**
 * Sends the owner a fresh Swiggy login link on WhatsApp, so renewing the
 * 5-day token takes one tap on a phone instead of a terminal.
 *
 * With an HTTPS redirect the callback finishes the login by itself. With the
 * localhost redirect the owner replies with the address the browser ends up
 * on, and the agent's `swiggy_login` tool finishes it.
 */
class LoginLink
{
    /** Minimum gap between unprompted links, so a failing token cannot spam the chat. */
    private const int THROTTLE_HOURS = 6;

    /** Start nudging this long before the token runs out. */
    private const int RENEW_BEFORE_HOURS = 12;

    public function __construct(private SwiggyAuth $auth, private Wacli $wacli) {}

    /**
     * The chat that receives login links and may finish a login.
     */
    public static function ownerJid(): ?string
    {
        return filled(config('services.swiggy.owner_jid')) ? (string) config('services.swiggy.owner_jid') : null;
    }

    public static function isOwnerChat(?string $chatJid): bool
    {
        return $chatJid !== null && $chatJid === self::ownerJid();
    }

    /**
     * Whether the saved login is missing, expired, or about to be.
     */
    public static function needsRenewal(): bool
    {
        $connection = Connection::current();

        return $connection === null
            || $connection->expires_at->lte(now()->addHours(self::RENEW_BEFORE_HOURS));
    }

    /**
     * Send a link unless one went out recently. Returns whether it was sent.
     */
    public function sendThrottled(): bool
    {
        if (! Cache::add('instamart:login-link-sent', true, now()->addHours(self::THROTTLE_HOURS))) {
            return false;
        }

        if (! $this->send()) {
            Cache::forget('instamart:login-link-sent');

            return false;
        }

        return true;
    }

    /**
     * Send a link now. Returns false when no owner chat is configured or
     * Swiggy would not start a login.
     */
    public function send(): bool
    {
        $owner = self::ownerJid();

        if ($owner === null) {
            return false;
        }

        try {
            $login = $this->auth->beginLogin();
        } catch (OAuthException $exception) {
            logger()->warning('instamart.login_link.failed', ['message' => $exception->getMessage()]);

            return false;
        }

        $this->wacli->send($owner, SwiggyAuth::usesWebCallback()
            ? "🔑 The Swiggy login needs renewing. Tap to log in with your phone and OTP, I'll finish the rest:\n\n{$login->url}"
            : "🔑 The Swiggy login needs renewing. Tap to log in with your phone and OTP:\n\n{$login->url}\n\nThe page after the OTP won't load, that's expected. Copy its full address and send it to me within 2 minutes.");

        return true;
    }

    /**
     * Tell the owner a login went through.
     */
    public function announceLoggedIn(Connection $connection): void
    {
        if (self::ownerJid() === null) {
            return;
        }

        $this->wacli->send(self::ownerJid(), sprintf('✅ Logged in to Swiggy. Valid until %s.', $connection->expires_at->toDayDateTimeString()));
    }
}
