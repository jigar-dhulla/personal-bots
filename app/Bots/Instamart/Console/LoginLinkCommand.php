<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Console;

use App\Bots\Instamart\Swiggy\LoginLink;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('instamart:login-link')]
#[Description('Send the owner a Swiggy login link on WhatsApp.')]
class LoginLinkCommand extends Command
{
    public function handle(LoginLink $link): int
    {
        if (LoginLink::ownerJid() === null) {
            $this->error('Set INSTAMART_OWNER_JID (or INSTAMART_CHATS) so there is a chat to send the link to.');

            return self::FAILURE;
        }

        if (! $link->send()) {
            $this->error('Could not start a Swiggy login. See the log.');

            return self::FAILURE;
        }

        $this->info('Login link sent.');

        return self::SUCCESS;
    }
}
