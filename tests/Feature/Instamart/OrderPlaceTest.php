<?php

declare(strict_types=1);

use App\Bots\Instamart\Enums\OrderStatus;
use App\Bots\Instamart\Enums\PaymentMethod;
use App\Bots\Instamart\Jobs\ConfirmUpiPayment;
use App\Bots\Instamart\Models\ChatAddress;
use App\Bots\Instamart\Models\Connection;
use App\Bots\Instamart\Models\Order;
use App\Bots\Instamart\Tools\OrderPlaceTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

const ORDER_CHAT = '918888888888@s.whatsapp.net';

beforeEach(function () {
    Connection::factory()->create();
    ChatAddress::factory()->create(['chat_jid' => ORDER_CHAT, 'address_id' => 'a1', 'address_line' => '12 MG Road']);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function fakeCheckoutReadyCart(string $toPay = '150', array $overrides = []): void
{
    fakeInstamart([
        'get_cart' => [
            'items' => [['spinId' => 'spin-1', 'skuId' => 'sku-1', 'itemName' => 'Milk', 'quantity' => 2, 'discountedFinalPrice' => 54, 'isInStockAndAvailable' => true]],
            'billBreakdown' => ['toPay' => ['label' => 'To pay', 'value' => $toPay]],
            'selectedAddressDetails' => ['address' => 'Flat 4, 12 MG Road, Bengaluru'],
        ],
        'get_payment_options' => [
            'cod' => ['available' => true, 'id' => 'cod', 'displayName' => 'Cash'],
            'platforms' => ['desktop' => ['groupName' => 'Scan QR', 'methods' => [['id' => 'qr']]]],
            'allMethods' => [],
        ],
        ...$overrides,
    ]);
}

function orderTool(string $sender = ORDER_CHAT): OrderPlaceTool
{
    return new OrderPlaceTool(chatJid: ORDER_CHAT, senderJid: $sender);
}

it('shows a summary and asks for confirmation before checking out', function () {
    fakeCheckoutReadyCart();

    $reply = (string) orderTool()->handle(new Request(['payment_method' => 'cash']));

    expect($reply)->toContain('Milk × 2')
        ->toContain('Deliver to: Flat 4, 12 MG Road, Bengaluru')
        ->toContain('Payment: Cash on delivery')
        ->toContain('Do you want to proceed with placing this order to this address?');
    expect(instamartCalls('checkout'))->toBeEmpty();
});

it('asks how to pay when several methods are available', function () {
    fakeCheckoutReadyCart();

    $reply = (string) orderTool()->handle(new Request([]));

    expect($reply)->toContain('How would you like to pay: Cash on delivery or UPI?');
});

it('does not check out when the model confirms in the same turn it prompted', function () {
    fakeCheckoutReadyCart();

    $tool = orderTool();
    $tool->handle(new Request(['payment_method' => 'cash']));
    $tool->handle(new Request(['payment_method' => 'cash', 'confirm' => true]));

    expect(instamartCalls('checkout'))->toBeEmpty();
});

it('places a cash order once the user confirms in a later message', function () {
    fakeCheckoutReadyCart(overrides: ['checkout' => ['orderId' => '777', 'status' => 'PLACED', 'paymentMethod' => 'COD', 'message' => 'Instamart order placed successfully']]);

    orderTool()->handle(new Request(['payment_method' => 'cash']));
    $reply = (string) orderTool()->handle(new Request(['confirm' => true]));

    expect(instamartCalls('checkout'))->toBe([['addressId' => 'a1', 'paymentMethod' => 'COD']]);
    expect($reply)->toContain('Instamart order placed successfully. Order #777');

    $order = Order::query()->sole();
    expect($order->status)->toBe(OrderStatus::Placed)
        ->and($order->payment_method)->toBe(PaymentMethod::Cash)
        ->and($order->cart_total)->toBe('150');
});

it('asks again instead of ordering when the cart changed since the summary', function () {
    $toPay = '150';
    fakeCheckoutReadyCart(overrides: ['get_cart' => function () use (&$toPay) {
        return [
            'items' => [['spinId' => 'spin-1', 'skuId' => 'sku-1', 'itemName' => 'Milk', 'quantity' => 2, 'isInStockAndAvailable' => true]],
            'billBreakdown' => ['toPay' => ['label' => 'To pay', 'value' => $toPay]],
        ];
    }]);

    orderTool()->handle(new Request(['payment_method' => 'cash']));

    $toPay = '200';
    $reply = (string) orderTool()->handle(new Request(['confirm' => true, 'payment_method' => 'cash']));

    expect(instamartCalls('checkout'))->toBeEmpty();
    expect($reply)->toContain('To pay: ₹200')->toContain('Reply *yes* to confirm');
});

it('only lets the person who saw the summary confirm it', function () {
    fakeCheckoutReadyCart();

    orderTool()->handle(new Request(['payment_method' => 'cash']));
    orderTool('917777777777@s.whatsapp.net')->handle(new Request(['payment_method' => 'cash', 'confirm' => true]));

    expect(instamartCalls('checkout'))->toBeEmpty();
});

it('sends a UPI payment link and follows the payment up in the background', function () {
    Queue::fake();
    fakeCheckoutReadyCart(overrides: ['checkout' => [
        'orderId' => '888', 'transactionId' => 't1', 'paasId' => 'paas-1', 'bridgeUrl' => 'https://pay.swiggy.com/b/xyz',
        'status' => 'PENDING_PAYMENT', 'paymentMethod' => 'UPI', 'pollingIntervalInMs' => 3000, 'maxTimeToPollForInMs' => 60000,
    ]]);

    orderTool()->handle(new Request(['payment_method' => 'upi']));
    $reply = (string) orderTool()->handle(new Request(['payment_method' => 'upi', 'confirm' => true]));

    expect(instamartCalls('checkout'))->toBe([['addressId' => 'a1', 'paymentMethod' => 'UPI', 'generateUPIQR' => true]]);
    expect($reply)->toContain('https://pay.swiggy.com/b/xyz');
    expect(Order::query()->sole())
        ->status->toBe(OrderStatus::PendingPayment)
        ->paas_id->toBe('paas-1');

    Queue::assertPushed(ConfirmUpiPayment::class, fn (ConfirmUpiPayment $job) => $job->order->order_id === '888' && $job->intervalMs === 3000);
});

it('does not retry a failed checkout and reports it was not placed when no new order appears', function () {
    Sleep::fake();
    fakeCheckoutReadyCart(overrides: [
        'checkout' => fn () => Http::response('', 503),
        'get_orders' => ['orders' => [['orderId' => '100']]],
    ]);

    orderTool()->handle(new Request(['payment_method' => 'cash']));
    $reply = (string) orderTool()->handle(new Request(['confirm' => true]));

    expect(instamartCalls('checkout'))->toHaveCount(1)
        ->and(instamartCalls('get_orders'))->toHaveCount(2);
    expect($reply)->toContain('the order was not placed');
    expect(Order::query()->count())->toBe(0);
    Sleep::assertSlept(fn ($duration) => $duration->totalSeconds === 3.0, 1);
});

it('records the order when a failed checkout went through after all', function () {
    Sleep::fake();
    $orderLists = [['orders' => [['orderId' => '100']]], ['orders' => [['orderId' => '101'], ['orderId' => '100']]]];
    fakeCheckoutReadyCart(overrides: [
        'checkout' => fn () => Http::response('', 504),
        'get_orders' => function () use (&$orderLists) {
            return array_shift($orderLists);
        },
    ]);

    orderTool()->handle(new Request(['payment_method' => 'cash']));
    $reply = (string) orderTool()->handle(new Request(['confirm' => true]));

    expect(instamartCalls('checkout'))->toHaveCount(1);
    expect($reply)->toContain('order #101 was placed');
    expect(Order::query()->sole())
        ->order_id->toBe('101')
        ->status->toBe(OrderStatus::Placed);
});

it('stays unsure when the order list cannot be read after a failed checkout', function () {
    Sleep::fake();
    fakeCheckoutReadyCart(overrides: [
        'checkout' => fn () => Http::response('', 503),
        'get_orders' => fn () => Http::response('', 400),
    ]);

    orderTool()->handle(new Request(['payment_method' => 'cash']));
    $reply = (string) orderTool()->handle(new Request(['confirm' => true]));

    expect(instamartCalls('checkout'))->toHaveCount(1);
    expect($reply)->toContain('Ask me for your order status before trying again');
    expect(Order::query()->count())->toBe(0);
});

it('does not record an order when checkout returns no order number', function () {
    fakeCheckoutReadyCart(overrides: ['checkout' => ['status' => 'UNKNOWN']]);

    orderTool()->handle(new Request(['payment_method' => 'cash']));
    $reply = (string) orderTool()->handle(new Request(['confirm' => true]));

    expect($reply)->toContain('did not return an order number');
    expect(Order::query()->count())->toBe(0);
});
