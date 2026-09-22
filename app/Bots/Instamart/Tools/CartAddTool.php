<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class CartAddTool extends InstamartTool
{
    public function name(): string
    {
        return 'cart_add';
    }

    public function description(): Stringable|string
    {
        return 'Add items from the most recent `product_search` results to the Instamart cart, by the numbers shown in those results. Adding an item already in the cart increases its quantity.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()
                ->description('The items to add, by their number in the latest search results.')
                ->items($schema->object([
                    'item_number' => $schema->integer()->description('The number shown next to the item in the search results.')->min(1)->required(),
                    'quantity' => $schema->integer()->description('How many to add. Defaults to 1.')->min(1)->max(20),
                ]))
                ->min(1)
                ->required(),
        ];
    }

    protected function respond(Request $request): string
    {
        $wanted = array_values(array_filter((array) ($request['items'] ?? []), 'is_array'));

        if ($wanted === []) {
            return 'Which items should I add? Give me their numbers from the search results.';
        }

        $picks = $this->picks();

        if ($picks === []) {
            return 'I have no recent search results for this chat. Tell me what to look for first.';
        }

        $address = $this->address();

        if (is_string($address)) {
            return $address;
        }

        $additions = [];
        $problems = [];

        foreach ($wanted as $item) {
            $number = (int) ($item['item_number'] ?? 0);
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $pick = $picks[$number] ?? null;

            if ($pick === null) {
                $problems[] = sprintf('There is no item #%d in the last search.', $number);

                continue;
            }

            if (! $pick['inStock']) {
                $problems[] = sprintf('%s is out of stock.', $pick['name']);

                continue;
            }

            $additions[] = ['spinId' => $pick['spinId'], 'skuId' => $pick['skuId'], 'quantity' => $quantity];
        }

        if ($additions === []) {
            return implode("\n", $problems);
        }

        $lines = $this->cartLines($this->cart());

        foreach ($additions as $addition) {
            $existing = array_search($addition['spinId'], array_column($lines, 'spinId'), true);

            if ($existing === false) {
                $lines[] = $addition;
            } else {
                $lines[$existing]['quantity'] += $addition['quantity'];
            }
        }

        $cart = $this->replaceCart($address, $lines);

        return implode("\n", [...$problems, "Cart updated:\n".$this->describeCart($cart)]);
    }
}
