<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Console;

use App\Bots\Instamart\Models\ChatAddress;
use App\Bots\Instamart\Models\Connection;
use App\Bots\Instamart\Swiggy\InstamartClient;
use App\Bots\Instamart\Swiggy\SwiggyAuth;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('instamart:logout')]
#[Description('Revoke the Instamart bot\'s Swiggy session, forget its token and the chats\' saved addresses.')]
class LogoutCommand extends Command
{
    public function handle(SwiggyAuth $auth, InstamartClient $client): int
    {
        $connection = Connection::current();

        if ($connection === null) {
            $this->info('Not logged in.');

            return self::SUCCESS;
        }

        $revoked = ! $connection->isExpired() && $auth->logout($connection);

        $client->forgetSession($connection);
        $connection->delete();

        // Address ids belong to the account that was just logged out.
        $addresses = ChatAddress::query()->delete();

        $this->info(($revoked ? 'Logged out of Swiggy.' : 'Forgot the saved Swiggy login.').sprintf(' Cleared %d saved chat address(es).', $addresses));

        return self::SUCCESS;
    }
}
