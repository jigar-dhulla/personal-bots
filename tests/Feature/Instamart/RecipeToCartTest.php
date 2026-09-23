<?php

declare(strict_types=1);

use App\Bots\Instamart\Models\ChatAddress;
use App\Bots\Instamart\Models\Connection;
use App\Bots\Instamart\Tools\CartAddTool;
use App\Bots\Instamart\Tools\RecipeToCartTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

const RECIPE_CHAT = '917777700000@s.whatsapp.net';

beforeEach(function () {
    Sleep::fake();
    Connection::factory()->create();
    ChatAddress::factory()->create(['chat_jid' => RECIPE_CHAT, 'address_id' => 'a1']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function recipeProduct(string $name, string $spinId, bool $inStock = true, array $overrides = []): array
{
    return [
        'displayName' => $name,
        'isPromoted' => false,
        'variations' => [[
            'spinId' => $spinId,
            'skuId' => 'sku-'.$spinId,
            'displayName' => $name,
            'quantityDescription' => '1 kg',
            'price' => ['mrp' => 120, 'offerPrice' => 99],
            'isInStockAndAvailable' => $inStock,
        ]],
        ...$overrides,
    ];
}

/**
 * Fake search results per query; unknown queries find nothing.
 *
 * @param  array<string, array<int, array<string, mixed>>|Closure>  $productsByQuery
 * @param  array<string, mixed>  $tools
 */
function fakeRecipeSearch(array $productsByQuery, array $tools = []): void
{
    fakeInstamart([
        'search_products' => function (array $arguments) use ($productsByQuery) {
            $products = $productsByQuery[$arguments['query']] ?? [];

            return $products instanceof Closure ? $products() : ['products' => $products];
        },
        ...$tools,
    ]);
}

function recipeTool(string $class = RecipeToCartTool::class): object
{
    return new $class(chatJid: RECIPE_CHAT, senderJid: RECIPE_CHAT);
}

it('picks one in-stock pack per ingredient as a numbered list', function () {
    fakeRecipeSearch([
        'basmati rice' => [recipeProduct('Basmati Rice', 'rice')],
        'green peas' => [recipeProduct('Frozen Peas', 'peas-out', inStock: false), recipeProduct('Green Peas', 'peas')],
        'ghee' => [recipeProduct('Ghee', 'ghee')],
    ]);

    $reply = (string) recipeTool()->handle(new Request([
        'dish' => 'veg pulav',
        'servings' => 4,
        'ingredients' => ['basmati rice', 'green peas', 'ghee'],
        'staples' => ['salt', 'turmeric'],
    ]));

    expect($reply)->toContain('For veg pulav (serves 4), from Instamart:')
        ->toContain('1. Basmati Rice 1 kg — ₹99 (MRP ₹120) ✅ (basmati rice)')
        ->toContain('2. Green Peas 1 kg — ₹99 (MRP ₹120) ✅ (green peas)')
        ->toContain('3. Ghee 1 kg — ₹99 (MRP ₹120) ✅ (ghee)')
        ->toContain('I skipped salt, turmeric, which most kitchens have.')
        ->toContain('Say "add all"');
    expect(array_column(instamartCalls('search_products'), 'query'))->toBe(['basmati rice', 'green peas', 'ghee']);
    expect(instamartCalls('update_cart'))->toBeEmpty();
});

it('prefers an unsponsored result over a sponsored one', function () {
    fakeRecipeSearch([
        'ghee' => [recipeProduct('Sponsored Ghee', 'ad', overrides: ['isPromoted' => true]), recipeProduct('Plain Ghee', 'plain')],
    ]);

    $reply = (string) recipeTool()->handle(new Request(['dish' => 'pulav', 'ingredients' => ['ghee']]));

    expect($reply)->toContain('1. Plain Ghee')->not->toContain('Sponsored Ghee');
});

it('lists ingredients that are out of stock or could not be searched', function () {
    fakeRecipeSearch([
        'basmati rice' => [recipeProduct('Basmati Rice', 'rice')],
        'bay leaf' => [recipeProduct('Bay Leaf', 'bay', inStock: false)],
        'cashews' => fn () => Http::response('', 503),
    ]);

    $reply = (string) recipeTool()->handle(new Request(['dish' => 'pulav', 'ingredients' => ['basmati rice', 'bay leaf', 'cashews']]));

    expect($reply)->toContain('1. Basmati Rice')
        ->toContain('Not in stock: bay leaf.')
        ->toContain('Swiggy did not answer for: cashews.');
});

it('searches each ingredient once and caps the list', function () {
    fakeRecipeSearch([]);

    recipeTool()->handle(new Request([
        'dish' => 'feast',
        'ingredients' => ['Onion', 'onion', ...array_map(fn (int $i) => "item {$i}", range(1, 20))],
    ]));

    expect(instamartCalls('search_products'))->toHaveCount(15)
        ->and(array_column(instamartCalls('search_products'), 'query'))->toContain('Onion')->not->toContain('onion');
});

it('says so when nothing for the dish is in stock', function () {
    fakeRecipeSearch([]);

    $reply = (string) recipeTool()->handle(new Request(['dish' => 'veg pulav', 'ingredients' => ['basmati rice']]));

    expect($reply)->toBe('I could not find any of the ingredients for veg pulav in stock on Instamart right now.');
});

it('adds every recipe pick to the cart in one update', function () {
    fakeRecipeSearch(
        [
            'basmati rice' => [recipeProduct('Basmati Rice', 'rice')],
            'green peas' => [recipeProduct('Green Peas', 'peas')],
        ],
        [
            'get_cart' => ['items' => [['spinId' => 'bread', 'skuId' => 'sku-bread', 'itemName' => 'Bread', 'quantity' => 1]]],
            'update_cart' => fn (array $arguments) => ['items' => array_map(fn (array $line) => $line + ['itemName' => $line['spinId']], $arguments['items'])],
        ],
    );

    recipeTool()->handle(new Request(['dish' => 'veg pulav', 'ingredients' => ['basmati rice', 'green peas']]));
    $reply = (string) recipeTool(CartAddTool::class)->handle(new Request(['item_numbers' => [1, 2]]));

    expect(instamartCalls('update_cart'))->toHaveCount(1)
        ->and(instamartCalls('update_cart')[0]['items'])->toBe([
            ['spinId' => 'bread', 'skuId' => 'sku-bread', 'quantity' => 1],
            ['spinId' => 'rice', 'skuId' => 'sku-rice', 'quantity' => 1],
            ['spinId' => 'peas', 'skuId' => 'sku-peas', 'quantity' => 1],
        ]);
    expect($reply)->toContain('Cart updated:');
});

it('reports unknown numbers but still adds the rest', function () {
    fakeRecipeSearch(
        ['ghee' => [recipeProduct('Ghee', 'ghee')]],
        ['update_cart' => fn (array $arguments) => ['items' => array_map(fn (array $line) => $line + ['itemName' => $line['spinId']], $arguments['items'])]],
    );

    recipeTool()->handle(new Request(['dish' => 'pulav', 'ingredients' => ['ghee']]));
    $reply = (string) recipeTool(CartAddTool::class)->handle(new Request(['item_numbers' => [1, 9]]));

    expect($reply)->toContain('There is no item #9 in the last results.')->toContain('Cart updated:');
    expect(instamartCalls('update_cart')[0]['items'])->toBe([['spinId' => 'ghee', 'skuId' => 'sku-ghee', 'quantity' => 1]]);
});

it('picks the single pack over a multi-pack Swiggy lists first', function () {
    fakeRecipeSearch(['basmati rice' => [[
        'displayName' => 'Basmati Rice',
        'variations' => [
            ['spinId' => 'rice-x2', 'skuId' => 'sku-x2', 'displayName' => 'Basmati Rice', 'quantityDescription' => '1 kg x 2', 'price' => ['mrp' => 400, 'offerPrice' => 332], 'isInStockAndAvailable' => true],
            ['spinId' => 'rice-1', 'skuId' => 'sku-1', 'displayName' => 'Basmati Rice', 'quantityDescription' => '1 kg', 'price' => ['mrp' => 200, 'offerPrice' => 170], 'isInStockAndAvailable' => true],
            ['spinId' => 'rice-500', 'skuId' => 'sku-500', 'displayName' => 'Basmati Rice', 'quantityDescription' => '500 g', 'price' => ['mrp' => 110, 'offerPrice' => 95], 'isInStockAndAvailable' => false],
        ],
    ]]]);

    $reply = (string) recipeTool()->handle(new Request(['dish' => 'pulav', 'ingredients' => ['basmati rice']]));

    expect($reply)->toContain('1. Basmati Rice 1 kg — ₹170 (MRP ₹200) ✅');
});
