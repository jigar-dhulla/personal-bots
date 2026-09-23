<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Tools;

use App\Bots\Instamart\Swiggy\SwiggyException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Turns a dish into a shopping list: the model supplies the ingredients (it
 * knows the recipe), and this tool searches Instamart for each one, picks a
 * single in-stock pack per ingredient, and numbers the picks as one list.
 * The list is remembered like a search, so "add all" or "add 1, 3 and 4"
 * goes through `cart_add`. Nothing is added to the cart here.
 */
class RecipeToCartTool extends InstamartTool
{
    /**
     * Each ingredient is one `search_products` call; this keeps a recipe well
     * inside Swiggy's 70 requests/minute and the queue job's time limit.
     */
    private const int MAX_INGREDIENTS = 15;

    /** How many search results to consider when choosing an ingredient's pack. */
    private const int CANDIDATE_PRODUCTS = 5;

    public function name(): string
    {
        return 'recipe_to_cart';
    }

    public function description(): Stringable|string
    {
        return 'Find the Instamart groceries for a dish the user wants to cook, e.g. "find and add items for veg pulav", "what do I need for paneer butter masala for 4?". You supply the ingredient list; the tool searches each one and replies with one numbered, in-stock pick per ingredient. Follow up with `cart_add` to put them in the cart.';
    }

    /**
     * Flat on purpose (lists of strings only): Gemini rejects nested objects
     * in tool schemas.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'dish' => $schema->string()
                ->description('The dish, as the user named it (e.g. "veg pulav").')
                ->required(),
            'servings' => $schema->integer()
                ->description('How many people it is for, when the user said so.')
                ->min(1)
                ->max(20),
            'ingredients' => $schema->array()
                ->description('What to buy for this dish, one short grocery search term each, most important first (e.g. ["basmati rice", "green peas", "carrot", "french beans", "onion", "ghee", "whole garam masala"]). Leave out water and the pantry staples listed in `staples`.')
                ->items($schema->string())
                ->min(1)
                ->max(self::MAX_INGREDIENTS)
                ->required(),
            'staples' => $schema->array()
                ->description('Pantry staples the recipe needs that you left out because most kitchens have them (e.g. ["salt", "turmeric", "oil"]). They are mentioned to the user, not searched.')
                ->items($schema->string()),
        ];
    }

    protected function respond(Request $request): string
    {
        $dish = trim((string) ($request['dish'] ?? ''));
        $ingredients = collect((array) ($request['ingredients'] ?? []))
            ->map(fn (mixed $ingredient): string => trim((string) $ingredient))
            ->filter()
            ->unique(fn (string $ingredient): string => mb_strtolower($ingredient))
            ->take(self::MAX_INGREDIENTS)
            ->values();

        if ($dish === '' || $ingredients->isEmpty()) {
            return 'Which dish, and what goes into it?';
        }

        $address = $this->address();

        if (is_string($address)) {
            return $address;
        }

        $picks = [];
        $lines = [];
        $notFound = [];
        $unsearched = [];

        foreach ($ingredients as $ingredient) {
            try {
                $results = $this->client()->call('search_products', [
                    'addressId' => $address->address_id,
                    'query' => $ingredient,
                ]);
            } catch (SwiggyException $exception) {
                if ($exception->needsLogin() || $exception->kind === SwiggyException::RATE_LIMITED) {
                    throw $exception;
                }

                $unsearched[] = $ingredient;

                continue;
            }

            $pick = $this->choosePick((array) ($results['products'] ?? []));

            if ($pick === null) {
                $notFound[] = $ingredient;

                continue;
            }

            $number = count($picks) + 1;
            $picks[$number] = $pick;
            $lines[] = $this->describePick($number, $pick).' ('.$ingredient.')';
        }

        if ($picks === []) {
            return sprintf('I could not find any of the ingredients for %s in stock on Instamart right now.', $dish);
        }

        $this->rememberPicks($picks);

        $servings = (int) ($request['servings'] ?? 0);
        $staples = array_values(array_filter(array_map('trim', array_map('strval', (array) ($request['staples'] ?? [])))));

        $reply = [sprintf('For %s%s, from Instamart:', $dish, $servings > 0 ? sprintf(' (serves %d)', $servings) : ''), ...$lines];

        if ($notFound !== []) {
            $reply[] = 'Not in stock: '.implode(', ', $notFound).'.';
        }

        if ($unsearched !== []) {
            $reply[] = 'Swiggy did not answer for: '.implode(', ', $unsearched).'. Ask me to search for them again.';
        }

        if ($staples !== []) {
            $reply[] = sprintf('I skipped %s, which most kitchens have. Say "add those too" if you need them.', implode(', ', $staples));
        }

        $reply[] = 'Say "add all", or e.g. "add 1, 3 and 4", to put them in the cart.';

        return implode("\n", $reply);
    }

    /**
     * One pack per ingredient, following Swiggy's own ranking: the first
     * product with a pack in stock, and within it the cheapest in-stock pack.
     * Swiggy often lists multi-packs first ("1 kg x 2"), and a recipe needs
     * one. Sponsored results are used only when nothing unsponsored is in
     * stock.
     *
     * @param  array<int, array<string, mixed>>  $products
     * @return array{spinId: string, skuId: string, name: string, inStock: bool, maxQuantity: int|null, offerPrice: mixed, mrp: mixed}|null
     */
    private function choosePick(array $products): ?array
    {
        $candidates = collect(array_slice($products, 0, self::CANDIDATE_PRODUCTS))
            ->sortBy(fn (array $product): int => ($product['isPromoted'] ?? false) === true ? 1 : 0);

        foreach ($candidates as $product) {
            $cheapest = collect((array) ($product['variations'] ?? []))
                ->map(fn (array $variation): ?array => $this->pickFromVariation($product, $variation))
                ->filter(fn (?array $pick): bool => $pick !== null && $pick['inStock'])
                ->sortBy(fn (array $pick): float => (float) ($pick['offerPrice'] ?? $pick['mrp'] ?? PHP_FLOAT_MAX))
                ->first();

            if ($cheapest !== null) {
                return $cheapest;
            }
        }

        return null;
    }
}
