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
        return 'Add items from the most recent `product_search` or `recipe_to_cart` results to the Instamart cart, by the numbers shown in those results. Pass every number the user wants in one call ("add all" means all of them). Adding an item already in the cart increases its quantity.';
    }

    /**
     * Kept flat on purpose: laravel/ai marks nested objects with
     * `additionalProperties`, which Gemini rejects for the whole request.
     * A list of plain integers is fine.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'item_numbers' => $schema->array()
                ->description('The numbers shown next to the items in the latest results, e.g. [3] for "add number 3", [1, 2, 3, 4, 5] for "add all 5".')
                ->items($schema->integer()->min(1))
                ->min(1)
                ->required(),
            'quantity' => $schema->integer()
                ->description('How many of each to add. Defaults to 1. For different quantities per item, call once per quantity.')
                ->min(1)
                ->max(20),
        ];
    }

    protected function respond(Request $request): string
    {
        $numbers = array_values(array_unique(array_filter(
            array_map('intval', (array) ($request['item_numbers'] ?? [])),
            fn (int $number): bool => $number > 0,
        )));
        $quantity = max(1, (int) ($request['quantity'] ?? 1));

        if ($numbers === []) {
            return 'Which items should I add? Give me their numbers from the results.';
        }

        $picks = $this->picks();

        if ($picks === []) {
            return 'I have no recent search results for this chat. Tell me what to look for first.';
        }

        $additions = [];
        $problems = [];

        foreach ($numbers as $number) {
            $pick = $picks[$number] ?? null;

            if ($pick === null) {
                $problems[] = sprintf('There is no item #%d in the last results.', $number);
            } elseif (! $pick['inStock']) {
                $problems[] = sprintf('%s is out of stock.', $pick['name']);
            } else {
                $additions[] = ['spinId' => $pick['spinId'], 'skuId' => $pick['skuId'], 'quantity' => $quantity];
            }
        }

        if ($additions === []) {
            return implode("\n", $problems);
        }

        $address = $this->address();

        if (is_string($address)) {
            return $address;
        }

        return implode("\n", [...$problems, "Cart updated:\n".$this->describeCart($this->addToCart($address, $additions))]);
    }
}
