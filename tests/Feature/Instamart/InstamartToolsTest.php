<?php

declare(strict_types=1);

use App\Bots\Instamart\Models\ChatAddress;
use App\Bots\Instamart\Models\Connection;
use App\Bots\Instamart\Tools\CartAddTool;
use App\Bots\Instamart\Tools\CartChangeTool;
use App\Bots\Instamart\Tools\CartViewTool;
use App\Bots\Instamart\Tools\DeliveryAddressTool;
use App\Bots\Instamart\Tools\OrderStatusTool;
use App\Bots\Instamart\Tools\ProductSearchTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

const INSTAMART_CHAT = '919999999999@s.whatsapp.net';

beforeEach(function () {
    Connection::factory()->create();
});

function milkSearch(): array
{
    return ['products' => [[
        'displayName' => 'Amul Taaza Milk',
        'variations' => [
            ['spinId' => 'spin-1', 'skuId' => 'sku-1', 'displayName' => 'Amul Taaza Milk', 'quantityDescription' => '500 ml', 'price' => ['mrp' => 28, 'offerPrice' => 27], 'isInStockAndAvailable' => true],
            ['spinId' => 'spin-2', 'skuId' => 'sku-2', 'displayName' => 'Amul Taaza Milk', 'quantityDescription' => '1 L', 'price' => ['mrp' => 54, 'offerPrice' => 54], 'isInStockAndAvailable' => false],
        ],
    ]]];
}

function instamartTool(string $class): object
{
    return new $class(chatJid: INSTAMART_CHAT, senderJid: INSTAMART_CHAT);
}

it('asks which address to use when the account has several', function () {
    fakeInstamart(['get_addresses' => ['addresses' => [
        ['id' => 'a1', 'addressLine' => '12 MG Road', 'addressTag' => 'Home'],
        ['id' => 'a2', 'addressLine' => '5 Tech Park', 'addressTag' => 'Work'],
    ]]]);

    $reply = instamartTool(ProductSearchTool::class)->handle(new Request(['query' => 'milk']));

    expect((string) $reply)->toContain('Which address')->toContain('1. Home: 12 MG Road')->toContain('2. Work: 5 Tech Park');
    expect(instamartCalls('search_products'))->toBeEmpty();
});

it('selects the only saved address and searches with live stock', function () {
    fakeInstamart([
        'get_addresses' => ['addresses' => [['id' => 'a1', 'addressLine' => '12 MG Road']]],
        'search_products' => milkSearch(),
    ]);

    $reply = (string) instamartTool(ProductSearchTool::class)->handle(new Request(['query' => 'milk']));

    expect($reply)->toContain('1. Amul Taaza Milk 500 ml — ₹27 (MRP ₹28) ✅')
        ->toContain('2. Amul Taaza Milk 1 L — ₹54 ❌ out of stock');
    expect(ChatAddress::forChat(INSTAMART_CHAT)->address_id)->toBe('a1');
    expect(instamartCalls('search_products')[0])->toBe(['addressId' => 'a1', 'query' => 'milk']);
});

it('adds a searched item on top of what is already in the cart', function () {
    ChatAddress::factory()->create(['chat_jid' => INSTAMART_CHAT, 'address_id' => 'a1']);
    fakeInstamart([
        'search_products' => milkSearch(),
        'get_cart' => ['items' => [['spinId' => 'spin-9', 'skuId' => 'sku-9', 'itemName' => 'Bread', 'quantity' => 1]]],
        'update_cart' => fn (array $arguments) => [
            'items' => array_map(fn (array $line) => $line + ['itemName' => $line['spinId'], 'discountedFinalPrice' => 10], $arguments['items']),
            'billBreakdown' => ['toPay' => ['label' => 'To pay', 'value' => '64']],
        ],
    ]);

    instamartTool(ProductSearchTool::class)->handle(new Request(['query' => 'milk']));
    $reply = (string) instamartTool(CartAddTool::class)->handle(new Request(['items' => [['item_number' => 1, 'quantity' => 2]]]));

    expect(instamartCalls('update_cart')[0])->toBe([
        'selectedAddressId' => 'a1',
        'items' => [
            ['spinId' => 'spin-9', 'skuId' => 'sku-9', 'quantity' => 1],
            ['spinId' => 'spin-1', 'skuId' => 'sku-1', 'quantity' => 2],
        ],
    ]);
    expect($reply)->toContain('To pay: ₹64');
});

it('refuses to add an out-of-stock variation', function () {
    ChatAddress::factory()->create(['chat_jid' => INSTAMART_CHAT, 'address_id' => 'a1']);
    fakeInstamart(['search_products' => milkSearch()]);

    instamartTool(ProductSearchTool::class)->handle(new Request(['query' => 'milk']));
    $reply = (string) instamartTool(CartAddTool::class)->handle(new Request(['items' => [['item_number' => 2]]]));

    expect($reply)->toContain('out of stock');
    expect(instamartCalls('update_cart'))->toBeEmpty();
});

it('asks for a search before adding when there are no results to pick from', function () {
    fakeInstamart();

    $reply = (string) instamartTool(CartAddTool::class)->handle(new Request(['items' => [['item_number' => 1]]]));

    expect($reply)->toContain('no recent search results');
});

it('clears the cart when removing its last line', function () {
    ChatAddress::factory()->create(['chat_jid' => INSTAMART_CHAT, 'address_id' => 'a1']);
    fakeInstamart(['get_cart' => ['items' => [['spinId' => 'spin-1', 'skuId' => 'sku-1', 'itemName' => 'Milk', 'quantity' => 1]]]]);

    $reply = (string) instamartTool(CartChangeTool::class)->handle(new Request(['line' => 1, 'quantity' => 0]));

    expect(instamartCalls('clear_cart'))->toHaveCount(1)
        ->and(instamartCalls('update_cart'))->toBeEmpty()
        ->and($reply)->toContain('The cart is empty.');
});

it('empties the cart when switching delivery address', function () {
    ChatAddress::factory()->create(['chat_jid' => INSTAMART_CHAT, 'address_id' => 'a1']);
    fakeInstamart(['get_addresses' => ['addresses' => [
        ['id' => 'a1', 'addressLine' => '12 MG Road'],
        ['id' => 'a2', 'addressLine' => '5 Tech Park'],
    ]]]);

    $reply = (string) instamartTool(DeliveryAddressTool::class)->handle(new Request(['choice' => 2]));

    expect($reply)->toContain('Delivering to: 5 Tech Park');
    expect(ChatAddress::forChat(INSTAMART_CHAT)->address_id)->toBe('a2');
    expect(instamartCalls('clear_cart'))->toHaveCount(1);
});

it('lists recent Instamart orders', function () {
    fakeInstamart(['get_orders' => ['orders' => [[
        'orderId' => '555', 'statusMessage' => 'Out for delivery', 'estimatedDeliveryTime' => '10 mins',
        'totalAmount' => 120, 'items' => [['name' => 'Milk', 'quantity' => 2]],
    ]]]]);

    $reply = (string) instamartTool(OrderStatusTool::class)->handle(new Request(['active_only' => true]));

    expect($reply)->toBe('#555 — Out for delivery — ETA 10 mins — ₹120 — Milk × 2');
    expect(instamartCalls('get_orders')[0])->toBe(['orderType' => 'INSTAMART', 'count' => 5, 'activeOnly' => true]);
});

it('tells the chat when the owner needs to log in again', function () {
    Connection::query()->delete();
    fakeInstamart();

    $reply = (string) instamartTool(OrderStatusTool::class)->handle(new Request([]));

    expect($reply)->toContain('php artisan instamart:login');
});

it('itemises the fees behind the amount to pay', function () {
    fakeInstamart(['get_cart' => [
        'items' => [['spinId' => 'spin-1', 'skuId' => 'sku-1', 'itemName' => 'Amul Taaza Tetra', 'itemVariant' => '200 ml', 'quantity' => 1, 'discountedFinalPrice' => 17]],
        'billBreakdown' => [
            'lineItems' => [['label' => 'Item Total', 'value' => '₹17.00'], ['label' => 'Small Cart Fee', 'value' => '₹20.00']],
            'toPay' => ['label' => 'To Pay', 'value' => '₹99'],
        ],
    ]]);

    $reply = (string) instamartTool(CartViewTool::class)->handle(new Request([]));

    expect($reply)->toContain("Item Total: ₹17.00\nSmall Cart Fee: ₹20.00\nTo pay: ₹99");
});
