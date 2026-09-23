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
        return 'Add one item from the most recent `product_search` results to the Instamart cart, by the number shown in those results. Call it once per item when the user wants several. Adding an item already in the cart increases its quantity.';
    }

    /**
     * Kept flat on purpose: laravel/ai marks nested objects with
     * `additionalProperties`, which Gemini rejects for the whole request.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'item_number' => $schema->integer()
                ->description('The number shown next to the item in the latest search results.')
                ->min(1)
                ->required(),
            'quantity' => $schema->integer()
                ->description('How many to add. Defaults to 1.')
                ->min(1)
                ->max(20),
        ];
    }

    protected function respond(Request $request): string
    {
        $number = (int) ($request['item_number'] ?? 0);
        $quantity = max(1, (int) ($request['quantity'] ?? 1));

        if ($number < 1) {
            return 'Which item should I add? Give me its number from the search results.';
        }

        $picks = $this->picks();

        if ($picks === []) {
            return 'I have no recent search results for this chat. Tell me what to look for first.';
        }

        $pick = $picks[$number] ?? null;

        if ($pick === null) {
            return sprintf('There is no item #%d in the last search.', $number);
        }

        if (! $pick['inStock']) {
            return sprintf('%s is out of stock.', $pick['name']);
        }

        $address = $this->address();

        if (is_string($address)) {
            return $address;
        }

        $lines = $this->cartLines($this->cart());
        $existing = array_search($pick['spinId'], array_column($lines, 'spinId'), true);

        if ($existing === false) {
            $lines[] = ['spinId' => $pick['spinId'], 'skuId' => $pick['skuId'], 'quantity' => $quantity];
        } else {
            $lines[$existing]['quantity'] += $quantity;
        }

        return "Cart updated:\n".$this->describeCart($this->replaceCart($address, $lines));
    }
}
