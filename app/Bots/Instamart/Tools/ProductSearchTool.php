<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class ProductSearchTool extends InstamartTool
{
    /** Products shown per search; each may list several pack sizes. */
    private const int MAX_PRODUCTS = 5;

    private const int MAX_VARIANTS_PER_PRODUCT = 3;

    private const int MAX_SIMILAR = 3;

    public function name(): string
    {
        return 'product_search';
    }

    public function description(): Stringable|string
    {
        return 'Search Instamart for products deliverable to this chat\'s address, with live price and stock. Use it for "find milk", "is Amul butter available?", "how much are eggs?". Results are numbered so the user can say "add 2 of number 3".';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('What to search for, in the user\'s words (e.g. "amul taaza milk", "brown bread").')
                ->required(),
        ];
    }

    protected function respond(Request $request): string
    {
        $query = trim((string) ($request['query'] ?? ''));

        if ($query === '') {
            return 'What should I search for?';
        }

        $address = $this->address();

        if (is_string($address)) {
            return $address;
        }

        $results = $this->client()->call('search_products', [
            'addressId' => $address->address_id,
            'query' => $query,
        ]);

        $picks = [];
        $lines = [];

        foreach (array_slice((array) ($results['products'] ?? []), 0, self::MAX_PRODUCTS) as $product) {
            $this->addProduct($product, self::MAX_VARIANTS_PER_PRODUCT, $picks, $lines);
        }

        $similar = array_slice((array) ($results['similarProducts'] ?? []), 0, self::MAX_SIMILAR);

        if ($similar !== []) {
            $lines[] = 'Similar items:';

            foreach ($similar as $product) {
                $this->addProduct($product, 1, $picks, $lines);
            }
        }

        if ($picks === []) {
            return sprintf('Nothing on Instamart matched "%s" for %s.', $query, $address->address_line);
        }

        $this->rememberPicks($picks);

        return sprintf("Instamart results for \"%s\":\n%s\n\nSay e.g. \"add 2 of number 1\" to put something in the cart.", $query, implode("\n", $lines));
    }

    /**
     * Number each sellable variation of a product (Swiggy carts take
     * variations, not parent products) and record its ids.
     *
     * @param  array<string, mixed>  $product
     * @param  array<int, array<string, mixed>>  $picks
     * @param  array<int, string>  $lines
     */
    private function addProduct(array $product, int $maxVariants, array &$picks, array &$lines): void
    {
        foreach (array_slice((array) ($product['variations'] ?? []), 0, $maxVariants) as $variation) {
            $pick = $this->pickFromVariation($product, $variation);

            if ($pick === null) {
                continue;
            }

            $number = count($picks) + 1;
            $picks[$number] = $pick;
            $lines[] = $this->describePick($number, $pick);
        }
    }
}
