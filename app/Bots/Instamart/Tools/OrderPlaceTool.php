<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Tools;

use App\Bots\Instamart\Enums\OrderStatus;
use App\Bots\Instamart\Enums\PaymentMethod;
use App\Bots\Instamart\Jobs\ConfirmUpiPayment;
use App\Bots\Instamart\Models\ChatAddress;
use App\Bots\Instamart\Models\Order;
use App\Bots\Instamart\Swiggy\SwiggyException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Places the cart as an order, in two steps. The first call reads the live
 * cart and payment options and asks the user to confirm; only a later call
 * — from a later message, by the same sender, against an unchanged cart —
 * actually checks out. Swiggy's `checkout` is not idempotent, so it is
 * never retried; a failed call is reconciled against the order list.
 */
class OrderPlaceTool extends InstamartTool
{
    /** How long a confirmation prompt stays valid. */
    private const int CONFIRMATION_TTL_MINUTES = 10;

    /** Fallbacks when a UPI checkout omits its polling hints. */
    private const int DEFAULT_POLL_INTERVAL_MS = 5_000;

    private const int DEFAULT_POLL_WINDOW_MS = 300_000;

    /** Swiggy suggests waiting 2–5 seconds before checking a failed checkout. */
    private const int RECONCILE_DELAY_SECONDS = 3;

    private const int RECONCILE_ORDER_COUNT = 5;

    /**
     * Set once this tool has shown a confirmation prompt, so the model cannot
     * prompt and confirm within the same inbound message.
     */
    private bool $promptedThisTurn = false;

    public function name(): string
    {
        return 'order_place';
    }

    public function description(): Stringable|string
    {
        return 'Place the Instamart cart as an order. First call it without `confirm` to show the order summary and ask the user to confirm. Only after the user replies yes in a later message, call it again with `confirm: true` to place the order.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'payment_method' => $schema->string()
                ->description('How the user wants to pay: "cash" (cash on delivery) or "upi" (a payment link). Omit if they have not said.')
                ->enum(array_column(PaymentMethod::cases(), 'value')),
            'confirm' => $schema->boolean()
                ->description('True only when the user has explicitly said yes to the order summary you showed them in an earlier message.'),
        ];
    }

    protected function respond(Request $request): string
    {
        $address = $this->address();

        if (is_string($address)) {
            return $address;
        }

        $cart = $this->cart();

        if (($cart['items'] ?? []) === [] || ($cart['cartAbsent'] ?? false) === true) {
            return 'The cart is empty. Search for something to add first.';
        }

        if ($this->hasUndeliverableItems($cart)) {
            return "Some items can't be delivered right now:\n".$this->describeCart($cart)."\n\nRemove them from the cart before ordering.";
        }

        $options = $this->client()->call('get_payment_options');
        $available = $this->availableMethods($options);

        if ($available === []) {
            return 'Swiggy is not offering any payment method for this cart right now. Try again in a little while.';
        }

        $method = PaymentMethod::tryFrom((string) ($request['payment_method'] ?? ''));

        if ($method === null && count($available) === 1) {
            $method = $available[0];
        }

        if ($method !== null && ! in_array($method, $available, true)) {
            return sprintf('%s is not available for this order. You can pay by: %s.', $method->label(), $this->labels($available));
        }

        $pending = Cache::get($this->pendingCacheKey());

        if (($request['confirm'] ?? false) === true && $this->matchesPending($pending, $address, $cart, $method)) {
            Cache::forget($this->pendingCacheKey());

            return $this->checkout($address, PaymentMethod::from($pending['method']), $options, $cart);
        }

        if ($method === null) {
            return $this->summary($address, $cart)."\n\nHow would you like to pay: ".$this->labels($available).'?';
        }

        Cache::put($this->pendingCacheKey(), [
            'sender' => $this->senderJid,
            'address' => $address->address_id,
            'amount' => $this->amountDue($cart),
            'method' => $method->value,
        ], now()->addMinutes(self::CONFIRMATION_TTL_MINUTES));

        $this->promptedThisTurn = true;

        return $this->summary($address, $cart)
            ."\nPayment: ".$method->label()
            ."\n\nDo you want to proceed with placing this order to this address? Reply *yes* to confirm.";
    }

    /**
     * A confirmation only counts against a prompt shown in an earlier message,
     * to the same sender, for the same address, amount and payment method.
     *
     * @param  array{sender: string, address: string, amount: string, method: string}|null  $pending
     * @param  array<string, mixed>  $cart
     */
    private function matchesPending(?array $pending, ChatAddress $address, array $cart, ?PaymentMethod $method): bool
    {
        return $pending !== null
            && ! $this->promptedThisTurn
            && $pending['sender'] === $this->senderJid
            && $pending['address'] === $address->address_id
            && $pending['amount'] === $this->amountDue($cart)
            && ($method === null || $method->value === $pending['method']);
    }

    /**
     * @param  array<string, mixed>  $cart
     */
    private function summary(ChatAddress $address, array $cart): string
    {
        $deliverTo = $cart['selectedAddressDetails']['address'] ?? $address->address_line;

        return "Your order:\n".$this->describeCart($cart)."\nDeliver to: ".$deliverTo;
    }

    /**
     * @param  array<string, mixed>  $cart
     */
    private function hasUndeliverableItems(array $cart): bool
    {
        return ($cart['unserviceableItems'] ?? []) !== []
            || collect($cart['items'] ?? [])->contains(fn (array $item): bool => ($item['isInStockAndAvailable'] ?? true) === false);
    }

    /**
     * Only offer what Swiggy returned: cash when COD is available, UPI when
     * any UPI app or QR method is listed.
     *
     * @param  array<string, mixed>  $options
     * @return array<int, PaymentMethod>
     */
    private function availableMethods(array $options): array
    {
        $methods = [];

        if (($options['cod']['available'] ?? false) === true) {
            $methods[] = PaymentMethod::Cash;
        }

        if ($this->upiMethods($options, 'desktop') !== [] || $this->upiMethods($options, 'mobile') !== []) {
            $methods[] = PaymentMethod::Upi;
        }

        return $methods;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, array{id: string}>
     */
    private function upiMethods(array $options, string $platform): array
    {
        return array_values((array) ($options['platforms'][$platform]['methods'] ?? []));
    }

    /**
     * @param  array<int, PaymentMethod>  $methods
     */
    private function labels(array $methods): string
    {
        return implode(' or ', array_map(fn (PaymentMethod $method): string => $method->label(), $methods));
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $cart
     */
    private function checkout(ChatAddress $address, PaymentMethod $method, array $options, array $cart): string
    {
        $arguments = ['addressId' => $address->address_id, 'paymentMethod' => $method->checkoutGroup()];

        if ($method === PaymentMethod::Upi) {
            // A scan-or-tap payment page works from a WhatsApp link on any device.
            if ($this->upiMethods($options, 'desktop') !== []) {
                $arguments['generateUPIQR'] = true;
            } else {
                $arguments['intentApp'] = (string) $this->upiMethods($options, 'mobile')[0]['id'];
            }
        }

        $knownOrderIds = $this->recentOrderIds();

        try {
            $result = $this->client()->call('checkout', $arguments, retryable: false);
        } catch (SwiggyException $exception) {
            if ($exception->isTransient()) {
                return $this->reconcileFailedCheckout($knownOrderIds, $method, $cart);
            }

            throw $exception;
        }

        $total = (string) ($result['cartTotal'] ?? $this->amountDue($cart));

        if (isset($result['orders'])) {
            return $this->multiStoreOutcome($result, $method, $total);
        }

        if ($method === PaymentMethod::Upi && filled($result['paasId'] ?? null) && filled($result['orderId'] ?? null)) {
            return $this->awaitUpi($result, $total);
        }

        if (blank($result['orderId'] ?? null)) {
            return 'Swiggy did not return an order number, so I cannot tell whether the order went through. Ask me for your order status before trying again.';
        }

        $this->record((string) $result['orderId'], $method, OrderStatus::Placed, $total);

        return sprintf('%s. Order #%s, %s, %s.', $result['message'] ?? 'Instamart order placed successfully', $result['orderId'], $this->money($total), $method->label());
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function awaitUpi(array $result, string $total): string
    {
        $order = $this->record((string) $result['orderId'], PaymentMethod::Upi, OrderStatus::PendingPayment, $total, (string) $result['paasId']);

        $interval = (int) ($result['pollingIntervalInMs'] ?? self::DEFAULT_POLL_INTERVAL_MS);
        $window = (int) ($result['maxTimeToPollForInMs'] ?? self::DEFAULT_POLL_WINDOW_MS);

        ConfirmUpiPayment::dispatch($order, Carbon::now()->addMilliseconds($window), $interval)
            ->delay(Carbon::now()->addMilliseconds($interval));

        return sprintf(
            "Order #%s is waiting for payment. Pay %s here: %s\nI'll confirm in this chat once the payment goes through.",
            $result['orderId'],
            $this->money($total),
            $result['bridgeUrl'] ?? $result['upiIntentUrl'] ?? '',
        );
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function multiStoreOutcome(array $result, PaymentMethod $method, string $total): string
    {
        $lines = [];

        foreach ((array) $result['orders'] as $order) {
            if (filled($order['orderId'] ?? null) && blank($order['error'] ?? null)) {
                $this->record((string) $order['orderId'], $method, OrderStatus::Placed, null);
                $lines[] = sprintf('✅ Order #%s placed', $order['orderId']);
            } else {
                $lines[] = '❌ '.($order['error'] ?? 'One store could not take the order');
            }
        }

        $headline = ($result['allSucceeded'] ?? false) === true
            ? sprintf('Your cart was split across %d stores and every order was placed (%s total, %s).', count($lines), $this->money($total), $method->label())
            : 'Your cart was split across stores and only part of it could be ordered:';

        return $headline."\n".implode("\n", $lines);
    }

    /**
     * The ids of the account's latest Instamart orders, or null when Swiggy
     * cannot say. Taken before checkout so a failed call can be reconciled.
     *
     * @return array<int, string>|null
     */
    private function recentOrderIds(): ?array
    {
        try {
            $orders = $this->client()->call('get_orders', ['orderType' => 'INSTAMART', 'count' => self::RECONCILE_ORDER_COUNT]);
        } catch (SwiggyException) {
            return null;
        }

        return collect($orders['orders'] ?? [])->pluck('orderId')->filter()->map(fn (mixed $id): string => (string) $id)->values()->all();
    }

    /**
     * Swiggy's rule for a checkout that failed upstream: never retry blind.
     * Wait briefly, look at the order list, and only then decide. An order id
     * that was not there before checkout means the order was placed after all.
     *
     * @param  array<int, string>|null  $knownOrderIds
     * @param  array<string, mixed>  $cart
     */
    private function reconcileFailedCheckout(?array $knownOrderIds, PaymentMethod $method, array $cart): string
    {
        $unsure = 'I could not confirm whether the order went through. Ask me for your order status before trying again, so it is not placed twice.';

        if ($knownOrderIds === null) {
            return $unsure;
        }

        Sleep::for(self::RECONCILE_DELAY_SECONDS)->seconds();

        $currentOrderIds = $this->recentOrderIds();

        if ($currentOrderIds === null) {
            return $unsure;
        }

        $placed = array_values(array_diff($currentOrderIds, $knownOrderIds));

        Log::warning('swiggy.checkout.reconciled', [
            'chat_jid' => $this->chatJid,
            'payment_method' => $method->value,
            'placed_order_ids' => $placed,
        ]);

        if ($placed === []) {
            return 'Swiggy had a hiccup and the order was not placed. Say "order it" when you want me to try again.';
        }

        foreach ($placed as $orderId) {
            $this->record($orderId, $method, $method === PaymentMethod::Upi ? OrderStatus::PendingPayment : OrderStatus::Placed, $this->amountDue($cart));
        }

        return $method === PaymentMethod::Upi
            ? sprintf('Swiggy was slow to answer, but order #%s was created. I lost the UPI payment link, so open the Swiggy app to pay for it.', implode(', #', $placed))
            : sprintf('Swiggy was slow to answer, but order #%s was placed (%s, %s).', implode(', #', $placed), $this->money($this->amountDue($cart)), $method->label());
    }

    private function record(string $orderId, PaymentMethod $method, OrderStatus $status, ?string $total, ?string $paasId = null): Order
    {
        return Order::query()->create([
            'order_id' => $orderId,
            'chat_jid' => $this->chatJid,
            'sender_jid' => $this->senderJid,
            'payment_method' => $method,
            'paas_id' => $paasId,
            'status' => $status,
            'cart_total' => $total,
        ]);
    }

    private function pendingCacheKey(): string
    {
        return 'instamart:pending-order:'.$this->chatJid;
    }
}
