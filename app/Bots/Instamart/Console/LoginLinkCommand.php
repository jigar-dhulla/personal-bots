<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Console;

use App\Bots\Instamart\Swiggy\LoginLink;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('instamart:login-link {--if-needed : Only send when the login is missing, expired or about to expire, and at most once every few hours}')]
#[Description('Send the owner a Swiggy login link on WhatsApp.')]
class LoginLinkCommand extends Command
{
    public function handle(LoginLink $link): int
    {
        if (LoginLink::ownerJid() === null) {
            $this->error('Set INSTAMART_OWNER_JID (or INSTAMART_CHATS) so there is a chat to send the link to.');

            return self::FAILURE;
        }

        if ($this->option('if-needed')) {
            if (! LoginLink::needsRenewal()) {
                return self::SUCCESS;
            }

            $this->line($link->sendThrottled() ? 'Login link sent.' : 'Skipped: a link went out recently or Swiggy refused.');

            return self::SUCCESS;
        }

        if (! $link->send()) {
            $this->error('Could not start a Swiggy login. See the log.');

            return self::FAILURE;
        }

        $this->info('Login link sent.');

        return self::SUCCESS;
    }
}
