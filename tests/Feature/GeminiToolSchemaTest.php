<?php

declare(strict_types=1);

use App\Bots\Bot;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Gateway\Gemini\Concerns\MapsTools;

/**
 * Gemini rejects the whole request when any tool's parameters contain
 * `additionalProperties`. laravel/ai adds it to every nested object and only
 * strips it at the top level, so a nested object schema breaks every
 * message to that bot. Run each tool through the real Gemini mapping.
 */
it('sends Gemini tool schemas without additionalProperties', function (string $manifest) {
    $mapper = new class
    {
        use MapsTools {
            mapTool as public;
        }
    };

    /** @var Bot $bot */
    $bot = new $manifest;
    $agentClass = $bot->agent();

    $rejected = collect((new $agentClass)->tools())
        ->filter(fn (Tool $tool): bool => str_contains((string) json_encode($mapper->mapTool($tool)), 'additionalProperties'))
        ->map(fn (Tool $tool): string => $tool->name())
        ->values()
        ->all();

    expect($rejected)->toBe([]);
})->with((require __DIR__.'/../../config/bots.php')['registered']);
