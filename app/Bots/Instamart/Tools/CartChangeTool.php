<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class CartChangeTool extends InstamartTool
{
    public function name(): string
    {
        return 'cart_change';
    }

    public function description(): Stringable|string
    {
        return 'Change the quantity of an item already in the cart, by its line number from `cart_view`. A quantity of 0 removes it.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'line' => $schema->integer()
                ->description('The line number of the item in the cart as last shown to the user.')
                ->min(1)
                ->required(),
            'quantity' => $schema->integer()
                ->description('The new total quantity for that line. 0 removes the item.')
                ->min(0)
                ->max(20)
                ->required(),
        ];
    }

    protected function respond(Request $request): string
    {
        $address = $this->address();

        if (is_string($address)) {
            return $address;
        }

        $cart = $this->cart();
        $lines = $this->cartLines($cart);
        $index = (int) ($request['line'] ?? 0) - 1;

        if (! isset($lines[$index])) {
            return "That line isn't in the cart. Here is what's there:\n".$this->describeCart($cart);
        }

        $lines[$index]['quantity'] = max(0, (int) ($request['quantity'] ?? 0));

        return "Cart updated:\n".$this->describeCart($this->replaceCart($address, $lines));
    }
}
