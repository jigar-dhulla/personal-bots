<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class CartViewTool extends InstamartTool
{
    public function name(): string
    {
        return 'cart_view';
    }

    public function description(): Stringable|string
    {
        return 'Show the current Instamart cart: numbered items, quantities, prices, warnings about unavailable items, and the amount to pay.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function respond(Request $request): string
    {
        return "Your Instamart cart:\n".$this->describeCart($this->cart());
    }
}
