<?php

declare(strict_types=1);

namespace App\Bots\Instamart;

use App\Bots\Bot;
use App\Bots\Instamart\Console\LoginCommand;
use App\Bots\Instamart\Console\LogoutCommand;
use App\Bots\Instamart\Enums\OrderStatus;
use App\Bots\Instamart\Models\Connection;
use App\Bots\Instamart\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class InstamartBot implements Bot
{
    public function key(): string
    {
        return 'instamart';
    }

    public function name(): string
    {
        return 'Instamart';
    }

    public function tagline(): string
    {
        return 'Grocery runs from your chat. Search Swiggy Instamart, check stock, fill a cart and order in plain language.';
    }

    public function agent(): string
    {
        return InstamartAgent::class;
    }

    /**
     * @return array<int, class-string<Command>>
     */
    public function commands(): array
    {
        return [LoginCommand::class, LogoutCommand::class];
    }

    /**
     * @return array<int, array{label: string, route: string, pattern: string}>
     */
    public function adminLinks(): array
    {
        return [];
    }

    /**
     * @return array<int, array{label: string, value: int, route: string|null, note: string}>
     */
    public function adminCards(): array
    {
        $connection = Connection::active();
        $orders = Order::query()->where('status', OrderStatus::Placed)->count();

        return [
            [
                'label' => 'Swiggy login',
                'value' => $connection === null ? 0 : (int) Carbon::now()->diffInDays($connection->expires_at),
                'route' => null,
                'note' => $connection === null
                    ? 'Not logged in or expired. Run `php artisan instamart:login`.'
                    : 'Days until the token expires ('.$connection->expires_at->toDayDateTimeString().').',
            ],
            [
                'label' => 'Orders placed',
                'value' => $orders,
                'route' => null,
                'note' => $orders === 0
                    ? 'No Instamart orders placed from WhatsApp yet.'
                    : Str::ucfirst(Str::plural('order', $orders)).' placed from WhatsApp.',
            ],
        ];
    }
}
