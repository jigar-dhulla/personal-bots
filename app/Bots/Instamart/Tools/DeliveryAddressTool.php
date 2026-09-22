<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Tools;

use App\Bots\Instamart\Models\ChatAddress;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class DeliveryAddressTool extends InstamartTool
{
    public function name(): string
    {
        return 'delivery_address';
    }

    public function description(): Stringable|string
    {
        return 'List the saved Swiggy delivery addresses, or choose which one this chat shops against. Call with no `choice` to show the numbered list; call with `choice` once the user picks a number. Switching address empties the cart.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'choice' => $schema->integer()
                ->description('The number of the address the user picked from the list you showed. Omit to just show the list.')
                ->min(1),
        ];
    }

    protected function respond(Request $request): string
    {
        $addresses = $this->savedAddresses();

        if ($addresses === []) {
            return 'There are no saved delivery addresses on the Swiggy account yet. Add one in the Swiggy app, then ask me again.';
        }

        $current = ChatAddress::forChat($this->chatJid);
        $choice = (int) ($request['choice'] ?? 0);

        if ($choice === 0) {
            return "Saved delivery addresses:\n".$this->formatAddresses($addresses, $current?->address_id)
                ."\n\nReply with a number to deliver there.";
        }

        $picked = $addresses[$choice - 1] ?? null;

        if ($picked === null) {
            return sprintf("There is no address #%d. Pick one of these:\n%s", $choice, $this->formatAddresses($addresses, $current?->address_id));
        }

        if ($current?->address_id === $picked['id']) {
            return 'Already delivering to: '.$picked['addressLine'];
        }

        // Carts are tied to their delivery address; Swiggy asks for a clear before switching.
        if ($current !== null) {
            $this->client()->call('clear_cart');
        }

        $this->forgetPicks();

        $this->selectAddress($picked);

        return 'Delivering to: '.$picked['addressLine'].($current !== null ? "\nThe previous cart was emptied, since carts belong to one address." : '');
    }
}
