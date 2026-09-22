<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class CartClearTool extends InstamartTool
{
    public function name(): string
    {
        return 'cart_clear';
    }

    public function description(): Stringable|string
    {
        return 'Empty the Instamart cart. Only when the user explicitly asks to clear, empty, or start the cart over.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function respond(Request $request): string
    {
        $this->client()->call('clear_cart');

        return 'The cart is empty now.';
    }
}
