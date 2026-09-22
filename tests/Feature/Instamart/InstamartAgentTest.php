<?php

declare(strict_types=1);

use App\Bots\Instamart\InstamartAgent;

it('introduces itself as the grocery assistant', function () {
    setAgentConfig(InstamartAgent::class);

    expect((string) (new InstamartAgent)->instructions())->toStartWith('You are the Instamart bot, a grocery assistant');
});

it('registers and describes every tool', function () {
    setAgentConfig(InstamartAgent::class);

    $names = collect((new InstamartAgent)->tools())->map(fn ($tool) => $tool->name())->all();
    $instructions = (string) (new InstamartAgent)->instructions();

    expect($names)->toEqualCanonicalizing([
        'product_search', 'cart_add', 'cart_view', 'cart_change', 'cart_clear',
        'delivery_address', 'order_place', 'order_status',
    ]);

    foreach ($names as $name) {
        expect($instructions)->toContain("`{$name}`");
    }
});

it('requires an explicit yes before placing an order', function () {
    setAgentConfig(InstamartAgent::class);

    expect((string) (new InstamartAgent)->instructions())
        ->toContain('Never call `order_place` with `confirm: true` unless the user explicitly said yes');
});
