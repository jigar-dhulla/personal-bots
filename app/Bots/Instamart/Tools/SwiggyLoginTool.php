<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Tools;

use App\Bots\Instamart\Swiggy\LoginLink;
use App\Bots\Instamart\Swiggy\SwiggyAuth;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use JigarDhulla\SwiggyMcp\Exceptions\OAuthException;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Renews the Swiggy login from WhatsApp: sends a fresh login link, or, when
 * the owner pastes the address their browser ended on, finishes the login.
 * Only the owner chat may use it, because whoever completes it decides
 * which Swiggy account the bot orders on.
 */
class SwiggyLoginTool extends InstamartTool
{
    public function name(): string
    {
        return 'swiggy_login';
    }

    public function description(): Stringable|string
    {
        return 'Renew the Swiggy login. Call with no arguments when the user asks to log in again or for a login link. Call with `callback_url` when the user sends back the web address (it contains `code=` and `state=`) their browser ended on after logging in.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'callback_url' => $schema->string()->description('The full web address the user pasted after logging in, exactly as sent. Omit to get a new login link.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        if (! LoginLink::isOwnerChat($this->chatJid)) {
            return 'Only the owner can renew the Swiggy login, from their own chat.';
        }

        $callbackUrl = trim((string) $request->string('callback_url'));

        if ($callbackUrl === '') {
            return app(LoginLink::class)->send()
                ? 'I sent the login link in a separate message.'
                : 'I could not start a Swiggy login right now. Try again in a minute.';
        }

        try {
            $connection = app(SwiggyAuth::class)->completeLogin($callbackUrl);
        } catch (OAuthException $exception) {
            return $exception->getMessage().' Ask me for a new login link.';
        }

        return sprintf('✅ Logged in to Swiggy. Valid until %s.', $connection->expires_at->toDayDateTimeString());
    }

    protected function respond(Request $request): string
    {
        return '';
    }
}
