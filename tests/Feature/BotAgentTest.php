<?php

declare(strict_types=1);

use Tests\Fixtures\EchoAgent;

function setBotAgentConfig(array $overrides = []): void
{
    config(['whatsapp-agent.agents' => [
        array_merge([
            'agent' => EchoAgent::class,
            'triggers' => [],
            'chats' => [],
            'groups' => [],
        ], $overrides),
    ]]);
}

it('opens the instructions with the agent persona', function () {
    setBotAgentConfig();

    expect((string) (new EchoAgent)->instructions())
        ->toStartWith('You are Echo, a test bot.');
});

it('includes the bot guidance between the context and the rules', function () {
    setBotAgentConfig();

    $instructions = (string) (new EchoAgent)->instructions();

    expect($instructions)->toContain('Call `echo_back` when the user says anything at all.')
        ->and(strpos($instructions, 'Current date and time:'))
        ->toBeLessThan(strpos($instructions, 'Call `echo_back`'))
        ->and(strpos($instructions, 'Call `echo_back`'))
        ->toBeLessThan(strpos($instructions, 'Rules:'));
});

it('injects the configured triggers into the instructions', function () {
    setBotAgentConfig(['triggers' => ['@echo', '@123456789']]);

    expect((string) (new EchoAgent)->instructions())
        ->toContain('You as agent are mentioned/triggered by these triggers: @echo, @123456789');
});

it('omits the triggers line when no triggers are configured', function () {
    setBotAgentConfig(['triggers' => []]);

    expect((string) (new EchoAgent)->instructions())
        ->not->toContain('You as agent are mentioned/triggered by these triggers');
});

it('only includes triggers for this agent class', function () {
    config(['whatsapp-agent.agents' => [
        ['agent' => 'App\\Bots\\Other\\OtherAgent', 'triggers' => ['@other']],
        ['agent' => EchoAgent::class, 'triggers' => ['@mine']],
    ]]);

    $instructions = (string) (new EchoAgent)->instructions();

    expect($instructions)->toContain('triggers: @mine')
        ->and($instructions)->not->toContain('@other');
});

it('omits the triggers line when the agent is absent from config', function () {
    config(['whatsapp-agent.agents' => [
        ['agent' => 'App\\Bots\\Other\\OtherAgent', 'triggers' => ['@other']],
    ]]);

    $instructions = (string) (new EchoAgent)->instructions();

    expect($instructions)->not->toContain('You as agent are mentioned/triggered by these triggers')
        ->and($instructions)->not->toContain('@other');
});

it('includes the current date and timezone for resolving relative phrases', function () {
    Carbon\Carbon::setTestNow('2026-06-02 09:30:00');
    setBotAgentConfig();

    $instructions = (string) (new EchoAgent)->instructions();

    expect($instructions)->toContain(Carbon\Carbon::now()->format('l, j F Y H:i'))
        ->and($instructions)->toContain(Carbon\Carbon::now()->timezoneName);

    Carbon\Carbon::setTestNow();
});

it('applies the WhatsApp etiquette rules every bot shares', function () {
    setBotAgentConfig();

    $instructions = (string) (new EchoAgent)->instructions();

    expect($instructions)->toContain('- Respond ONLY to people who have mentioned/triggered you.')
        ->and($instructions)->toContain('- Act ONLY on the most recent message that triggered you.')
        ->and($instructions)->toContain('- Keep replies concise and WhatsApp-friendly')
        ->and($instructions)->toContain('- Do not invent details.');
});

it('appends the bot-specific rules after the shared ones', function () {
    setBotAgentConfig();

    $instructions = (string) (new EchoAgent)->instructions();

    expect($instructions)->toEndWith('- Echo the message back verbatim.')
        ->and(strpos($instructions, '- Respond ONLY to people'))
        ->toBeLessThan(strpos($instructions, '- Echo the message back verbatim.'));
});
