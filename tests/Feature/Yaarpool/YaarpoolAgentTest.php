<?php

declare(strict_types=1);

use App\Bots\Yaarpool\YaarpoolAgent;

it('introduces itself as the ridesharing assistant', function () {
    setAgentConfig(YaarpoolAgent::class);

    expect((string) (new YaarpoolAgent)->instructions())
        ->toStartWith('You are Yaarpool, a friendly ridesharing assistant');
});

it('injects the configured triggers into the instructions', function () {
    setAgentConfig(YaarpoolAgent::class, ['triggers' => ['@yaarpool', '@123456789']]);

    expect((string) (new YaarpoolAgent)->instructions())
        ->toContain('You as agent are mentioned/triggered by these triggers: @yaarpool, @123456789');
});

it('describes every tool the agent can call', function () {
    setAgentConfig(YaarpoolAgent::class);

    $instructions = (string) (new YaarpoolAgent)->instructions();

    foreach (['ride_request', 'ride_create', 'ride_list', 'ride_join', 'ride_update', 'ride_delete', 'route_travellers', 'user_settings'] as $tool) {
        expect($instructions)->toContain("`{$tool}`");
    }
});

it('registers a tool for every documented capability', function () {
    $names = collect((new YaarpoolAgent)->tools())->map(fn ($tool) => $tool->name())->all();

    expect($names)->toEqualCanonicalizing([
        'ride_request', 'ride_create', 'ride_list', 'ride_join',
        'ride_update', 'ride_delete', 'user_settings', 'route_travellers',
    ]);
});

it('keeps the ownership and default-location rules', function () {
    setAgentConfig(YaarpoolAgent::class);

    $instructions = (string) (new YaarpoolAgent)->instructions();

    expect($instructions)->toContain('Ownership: only the original poster can edit or cancel a ride.')
        ->and($instructions)->toContain('Default locations:')
        ->and($instructions)->toContain('Locations are the exception to not guessing')
        ->and($instructions)->toContain('Always populate `when_text` with the user\'s exact phrasing of the time');
});

it('inherits the shared rule about responding only to people who triggered it', function () {
    setAgentConfig(YaarpoolAgent::class);

    expect((string) (new YaarpoolAgent)->instructions())
        ->toContain('Respond ONLY to people who have mentioned/triggered you.');
});

it('includes the current date and timezone for resolving relative phrases', function () {
    Carbon\Carbon::setTestNow('2026-06-02 09:30:00');
    setAgentConfig(YaarpoolAgent::class);

    $instructions = (string) (new YaarpoolAgent)->instructions();

    expect($instructions)->toContain(Carbon\Carbon::now()->format('l, j F Y H:i'))
        ->and($instructions)->toContain(Carbon\Carbon::now()->timezoneName);

    Carbon\Carbon::setTestNow();
});
