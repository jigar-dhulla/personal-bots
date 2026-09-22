<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class OrderStatusTool extends InstamartTool
{
    private const int MAX_ORDERS = 5;

    public function name(): string
    {
        return 'order_status';
    }

    public function description(): Stringable|string
    {
        return 'Show recent Instamart orders on the account (last 15 days) with their status and ETA. Use for "where is my order?", "did the order go through?", "what did I order?".';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'active_only' => $schema->boolean()
                ->description('True to show only orders still on their way (e.g. "where is my order?"). Defaults to false.'),
        ];
    }

    protected function respond(Request $request): string
    {
        $activeOnly = ($request['active_only'] ?? false) === true;

        $orders = array_values((array) ($this->client()->call('get_orders', [
            'orderType' => 'INSTAMART',
            'count' => self::MAX_ORDERS,
            'activeOnly' => $activeOnly,
        ])['orders'] ?? []));

        if ($orders === []) {
            return $activeOnly ? 'No Instamart orders are on their way right now.' : 'No Instamart orders in the last 15 days.';
        }

        return collect($orders)->map(function (array $order): string {
            $items = collect($order['items'] ?? [])
                ->map(fn (array $item): string => sprintf('%s × %d', $item['name'], (int) ($item['quantity'] ?? 1)))
                ->implode(', ');

            return implode(' — ', array_filter([
                '#'.$order['orderId'],
                $order['statusMessage'] ?? $order['currentStatus'] ?? $order['status'] ?? null,
                filled($order['estimatedDeliveryTime'] ?? null) ? 'ETA '.$order['estimatedDeliveryTime'] : null,
                isset($order['totalAmount']) ? $this->money($order['totalAmount']) : null,
                $items !== '' ? $items : null,
            ]));
        })->implode("\n");
    }
}
