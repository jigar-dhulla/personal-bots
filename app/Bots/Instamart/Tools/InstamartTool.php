<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Tools;

use App\Bots\Instamart\Models\ChatAddress;
use App\Bots\Instamart\Swiggy\InstamartClient;
use App\Bots\Instamart\Swiggy\SwiggyException;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Shared plumbing for the Instamart tools: a Swiggy client, the chat's
 * delivery address, the numbered picks from the last search, cart
 * formatting, and turning Swiggy failures into WhatsApp-friendly replies.
 *
 * WhatsApp history only carries what the bot said, not the tool results
 * behind it, so anything the user refers back to by number ("add 2 of item
 * 3") is kept server-side per chat rather than trusted to the model.
 */
abstract class InstamartTool implements Tool
{
    /** How long a chat's last search results stay addressable by number. */
    protected const int PICKS_TTL_HOURS = 3;

    public function __construct(
        protected ?string $chatJid = null,
        protected ?string $senderJid = null,
    ) {}

    /**
     * Produce the tool's reply. Swiggy failures are caught by {@see handle()}.
     *
     * @throws SwiggyException
     */
    abstract protected function respond(Request $request): string;

    public function handle(Request $request): Stringable|string
    {
        if ($this->chatJid === null || $this->senderJid === null) {
            return 'I cannot tell which chat this is, so I can\'t shop on Instamart right now.';
        }

        try {
            return $this->respond($request);
        } catch (SwiggyException $exception) {
            return $this->describeFailure($exception);
        }
    }

    protected function client(): InstamartClient
    {
        return app(InstamartClient::class);
    }

    protected function describeFailure(SwiggyException $exception): string
    {
        return match (true) {
            $exception->needsLogin() => 'My Swiggy login has expired. The owner needs to run `php artisan instamart:login` before I can shop again.',
            $exception->kind === SwiggyException::RATE_LIMITED => 'Swiggy is asking me to slow down. Try again in a minute.',
            $exception->isTransient() => 'Swiggy is not responding right now. Try again in a little while.',
            default => 'Swiggy says: '.$exception->getMessage(),
        };
    }

    /**
     * The chat's delivery address, or a message asking the user to pick one.
     * A Swiggy account with exactly one saved address is selected silently.
     */
    protected function address(): ChatAddress|string
    {
        $address = ChatAddress::forChat($this->chatJid);

        if ($address !== null) {
            return $address;
        }

        $addresses = $this->savedAddresses();

        if ($addresses === []) {
            return 'There are no saved delivery addresses on the Swiggy account yet. Add one in the Swiggy app, then ask me again.';
        }

        if (count($addresses) === 1) {
            return $this->selectAddress($addresses[0]);
        }

        return "Which address should I deliver to? Reply with its number:\n".$this->formatAddresses($addresses);
    }

    /**
     * @return array<int, array{id: string, addressLine: string, addressTag?: string, addressCategory?: string}>
     */
    protected function savedAddresses(): array
    {
        return array_values((array) ($this->client()->call('get_addresses', ['pageSize' => 10])['addresses'] ?? []));
    }

    /**
     * @param  array{id: string, addressLine: string}  $address
     */
    protected function selectAddress(array $address): ChatAddress
    {
        return ChatAddress::query()->updateOrCreate(
            ['chat_jid' => $this->chatJid],
            ['address_id' => (string) $address['id'], 'address_line' => (string) $address['addressLine']],
        );
    }

    /**
     * @param  array<int, array{id: string, addressLine: string, addressTag?: string, addressCategory?: string}>  $addresses
     */
    protected function formatAddresses(array $addresses, ?string $currentId = null): string
    {
        return collect($addresses)->values()->map(function (array $address, int $index) use ($currentId): string {
            $tag = $address['addressTag'] ?? $address['addressCategory'] ?? null;

            return sprintf(
                '%d. %s%s%s',
                $index + 1,
                filled($tag) ? $tag.': ' : '',
                $address['addressLine'],
                $currentId !== null && $address['id'] === $currentId ? ' (current)' : '',
            );
        })->implode("\n");
    }

    /**
     * Remember the numbered variants from a search so a later message can
     * refer to them by number.
     *
     * @param  array<int, array<string, mixed>>  $picks  keyed by the number shown to the user
     */
    protected function rememberPicks(array $picks): void
    {
        Cache::put($this->picksCacheKey(), $picks, now()->addHours(self::PICKS_TTL_HOURS));
    }

    /**
     * @return array<int, array{spinId: string, skuId: string, name: string, inStock: bool, maxQuantity: int|null, offerPrice: mixed, mrp: mixed}>
     */
    protected function picks(): array
    {
        return (array) Cache::get($this->picksCacheKey(), []);
    }

    /**
     * A search result's variation as a pick: the ids the cart needs plus what
     * the user sees. Null for variations Swiggy returned without cart ids.
     *
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $variation
     * @return array{spinId: string, skuId: string, name: string, inStock: bool, maxQuantity: int|null, offerPrice: mixed, mrp: mixed}|null
     */
    protected function pickFromVariation(array $product, array $variation): ?array
    {
        if (blank($variation['spinId'] ?? null) || blank($variation['skuId'] ?? null)) {
            return null;
        }

        return [
            'spinId' => (string) $variation['spinId'],
            'skuId' => (string) $variation['skuId'],
            'name' => trim(($variation['displayName'] ?? $product['displayName'] ?? 'Item').' '.($variation['quantityDescription'] ?? '')),
            'inStock' => (bool) ($variation['isInStockAndAvailable'] ?? false),
            'maxQuantity' => isset($variation['maxQuantity']) ? (int) $variation['maxQuantity'] : null,
            'offerPrice' => $variation['price']['offerPrice'] ?? null,
            'mrp' => $variation['price']['mrp'] ?? null,
        ];
    }

    /**
     * One numbered line for a pick: name, price (with MRP when discounted)
     * and stock.
     *
     * @param  array{name: string, inStock: bool, offerPrice: mixed, mrp: mixed}  $pick
     */
    protected function describePick(int $number, array $pick): string
    {
        $offer = $pick['offerPrice'];
        $mrp = $pick['mrp'];

        return sprintf(
            '%d. %s — %s%s %s',
            $number,
            $pick['name'],
            $this->money($offer ?? $mrp),
            $mrp !== null && $offer !== null && $mrp > $offer ? ' (MRP '.$this->money($mrp).')' : '',
            $pick['inStock'] ? '✅' : '❌ out of stock',
        );
    }

    /**
     * Add picks to the live cart in one `update_cart`. A pick already in the
     * cart gets its quantity increased rather than a second line.
     *
     * @param  array<int, array{spinId: string, skuId: string, quantity: int}>  $additions
     * @return array<string, mixed>
     */
    protected function addToCart(ChatAddress $address, array $additions): array
    {
        $lines = $this->cartLines($this->cart());

        foreach ($additions as $addition) {
            $existing = array_search($addition['spinId'], array_column($lines, 'spinId'), true);

            if ($existing === false) {
                $lines[] = $addition;
            } else {
                $lines[$existing]['quantity'] += $addition['quantity'];
            }
        }

        return $this->replaceCart($address, $lines);
    }

    protected function forgetPicks(): void
    {
        Cache::forget($this->picksCacheKey());
    }

    private function picksCacheKey(): string
    {
        return 'instamart:picks:'.$this->chatJid;
    }

    /**
     * The live cart. Swiggy advises re-reading it on every turn rather than
     * caching, since carts expire and stock changes.
     *
     * @return array<string, mixed>
     */
    protected function cart(): array
    {
        return $this->client()->call('get_cart');
    }

    /**
     * @param  array<string, mixed>  $cart
     * @return array<int, array{spinId: string, skuId: string, quantity: int}>
     */
    protected function cartLines(array $cart): array
    {
        return collect($cart['items'] ?? [])
            ->map(fn (array $item): array => [
                'spinId' => (string) $item['spinId'],
                'skuId' => (string) $item['skuId'],
                'quantity' => (int) $item['quantity'],
            ])
            ->values()
            ->all();
    }

    /**
     * `update_cart` replaces the whole cart, so callers always send the full
     * list. An empty list clears it instead.
     *
     * @param  array<int, array{spinId: string, skuId: string, quantity: int}>  $lines
     * @return array<string, mixed>
     */
    protected function replaceCart(ChatAddress $address, array $lines): array
    {
        $lines = array_values(array_filter($lines, fn (array $line): bool => $line['quantity'] > 0));

        if ($lines === []) {
            $this->client()->call('clear_cart');

            return ['items' => []];
        }

        return $this->client()->call('update_cart', [
            'selectedAddressId' => $address->address_id,
            'items' => $lines,
        ]);
    }

    /**
     * A WhatsApp-sized cart summary: numbered lines, warnings, and the total.
     *
     * @param  array<string, mixed>  $cart
     */
    protected function describeCart(array $cart): string
    {
        $items = array_values((array) ($cart['items'] ?? []));

        if ($items === [] || ($cart['cartAbsent'] ?? false) === true) {
            return 'The cart is empty.';
        }

        $lines = collect($items)->map(fn (array $item, int $index): string => sprintf(
            '%d. %s%s × %d — %s%s',
            $index + 1,
            $item['itemName'],
            filled($item['itemVariant'] ?? null) ? ' ('.$item['itemVariant'].')' : '',
            (int) $item['quantity'],
            $this->money($item['discountedFinalPrice'] ?? $item['mrp'] ?? null),
            ($item['isInStockAndAvailable'] ?? true) ? '' : ' ⚠️ unavailable',
        ));

        foreach ((array) ($cart['removedOutOfStockItems'] ?? []) as $item) {
            $lines->push(sprintf('Removed (out of stock): %s', $item['itemName'] ?? 'an item'));
        }

        foreach ((array) ($cart['reducedQuantityItems'] ?? []) as $item) {
            $lines->push(sprintf('Capped %s at %d (asked for %d)', $item['itemName'] ?? 'an item', (int) ($item['cappedQuantity'] ?? 0), (int) ($item['requestedQuantity'] ?? 0)));
        }

        foreach ((array) ($cart['unserviceableItems'] ?? []) as $item) {
            $lines->push(sprintf('Not deliverable to this address: %s', $item['itemName'] ?? 'an item'));
        }

        foreach (array_filter([$cart['addressWarning'] ?? null, $cart['cartWarning']['message'] ?? null]) as $warning) {
            $lines->push('⚠️ '.$warning);
        }

        foreach ((array) ($cart['billBreakdown']['lineItems'] ?? []) as $charge) {
            if (filled($charge['label'] ?? null) && filled($charge['value'] ?? null)) {
                $lines->push(sprintf('%s: %s', $charge['label'], $this->money($charge['value'])));
            }
        }

        $toPay = $cart['billBreakdown']['toPay']['value'] ?? $cart['cartTotalAmount'] ?? null;

        if ($toPay !== null) {
            $lines->push('To pay: '.$this->money($toPay));
        }

        return $lines->implode("\n");
    }

    /**
     * The amount due, as Swiggy reports it — used to detect a cart that
     * changed between the confirmation prompt and the checkout.
     *
     * @param  array<string, mixed>  $cart
     */
    protected function amountDue(array $cart): string
    {
        return (string) ($cart['billBreakdown']['toPay']['value'] ?? $cart['cartTotalAmount'] ?? '');
    }

    protected function money(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }

        $amount = (string) $amount;

        return str_starts_with($amount, '₹') ? $amount : '₹'.$amount;
    }
}
