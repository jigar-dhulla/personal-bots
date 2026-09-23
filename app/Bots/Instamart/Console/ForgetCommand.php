<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Console;

use App\Bots\Instamart\Models\ChatAddress;
use App\Bots\Instamart\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

use function Laravel\Prompts\confirm;

#[Signature('instamart:forget
    {--chat= : Only forget data for this chat JID}
    {--force : Skip the confirmation prompt}')]
#[Description('Delete what the Instamart bot stores about chats: chosen delivery addresses, order records, and cached search results.')]
class ForgetCommand extends Command
{
    public function handle(): int
    {
        $chatJid = $this->option('chat');
        $scope = filled($chatJid) ? 'chat '.$chatJid : 'every chat';

        $addresses = ChatAddress::query()->when(filled($chatJid), fn ($query) => $query->where('chat_jid', $chatJid));
        $orders = Order::query()->when(filled($chatJid), fn ($query) => $query->where('chat_jid', $chatJid));

        $chatJids = $addresses->clone()->pluck('chat_jid')
            ->merge($orders->clone()->pluck('chat_jid'))
            ->when(filled($chatJid), fn ($jids) => $jids->push($chatJid))
            ->unique();

        if (! $this->option('force') && ! confirm(sprintf(
            'Delete %d saved address(es) and %d order record(s) for %s?',
            $addresses->clone()->count(),
            $orders->clone()->count(),
            $scope,
        ), default: false)) {
            $this->info('Nothing deleted.');

            return self::SUCCESS;
        }

        $deletedAddresses = $addresses->delete();
        $deletedOrders = $orders->delete();

        foreach ($chatJids as $jid) {
            Cache::forget('instamart:picks:'.$jid);
            Cache::forget('instamart:pending-order:'.$jid);
        }

        $this->info(sprintf('Deleted %d address(es) and %d order record(s) for %s. Orders stay on your Swiggy account.', $deletedAddresses, $deletedOrders, $scope));

        return self::SUCCESS;
    }
}
