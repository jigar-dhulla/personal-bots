<?php

declare(strict_types=1);

namespace App\Bots\Yaarpool\Tools;

use App\Bots\Yaarpool\Models\Ride;
use App\Bots\Yaarpool\Models\UserSetting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RouteTravellersTool implements Tool
{
    public function __construct(
        protected ?string $chatJid = null,
    ) {}

    public function name(): string
    {
        return 'route_travellers';
    }

    public function description(): Stringable|string
    {
        return 'List people in this chat who have saved a usual route in their personal settings, optionally filtered by where they travel from and/or to. Use this to see who regularly commutes a route — it works from saved defaults, so no ride needs to have been posted.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($this->chatJid === null) {
            return 'I can only look up regular travellers inside a specific chat.';
        }

        $from = $this->normalize($request['from'] ?? null);
        $to = $this->normalize($request['to'] ?? null);

        // Saved personal defaults are the source of truth for a usual route, so
        // start from user_settings rather than gating on ride history.
        $routes = UserSetting::query()
            ->whereNotNull('default_from_location')
            ->get()
            ->filter(fn (UserSetting $setting): bool => $this->matchesRoute($setting, $from, $to));

        if ($routes->isEmpty()) {
            return $this->emptyMessage($from, $to);
        }

        // Then check past ride history: only surface people known to this chat
        // (as a poster or a passenger) so a saved route never leaks into a
        // group the person has never been part of.
        $namesBySender = Ride::knownSendersForChat($this->chatJid)->pluck('sender_name', 'sender_jid');

        $matches = $routes
            ->filter(fn (UserSetting $setting): bool => $namesBySender->has($setting->sender_jid))
            ->map(fn (UserSetting $setting): string => $this->line($setting, $namesBySender->get($setting->sender_jid)))
            ->values();

        if ($matches->isEmpty()) {
            return $this->emptyMessage($from, $to);
        }

        return $matches->implode("\n");
    }

    /**
     * Whether a saved route matches the requested from/to filters. Both sides
     * are compared case-insensitively and by partial text, so "wakad" matches
     * a saved "Wakad Station". A null filter matches anything.
     */
    private function matchesRoute(UserSetting $setting, ?string $from, ?string $to): bool
    {
        if ($from !== null && ! Str::contains($this->normalize($setting->default_from_location) ?? '', $from)) {
            return false;
        }

        if ($to !== null && ! Str::contains($this->normalize($setting->default_to_location) ?? '', $to)) {
            return false;
        }

        return true;
    }

    /**
     * A one-line "Name: route (in office days, hours)" summary of a saved route.
     */
    private function line(UserSetting $setting, ?string $name): string
    {
        $route = $setting->default_to_location === null
            ? sprintf('from %s', $setting->default_from_location)
            : sprintf('%s → %s', $setting->default_from_location, $setting->default_to_location);

        $days = $setting->officeDaysLabel();
        $hours = $setting->officeHoursLabel();

        $extra = collect([
            $days === null ? null : "in office {$days}",
            $hours,
        ])->filter()->implode(', ');

        $name = ($name === null || trim($name) === '') ? 'Someone' : $name;

        return $extra === ''
            ? sprintf('%s: %s', $name, $route)
            : sprintf('%s: %s (%s)', $name, $route, $extra);
    }

    private function emptyMessage(?string $from, ?string $to): string
    {
        if ($from !== null || $to !== null) {
            return 'No one in this chat has saved that route in their personal defaults yet.';
        }

        return 'No one in this chat has saved a usual route yet. They can tell me e.g. "remember I travel from Wakad to Hinjewadi".';
    }

    private function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = Str::lower(trim($value));

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()
                ->description('Only list people whose usual starting location matches this (e.g. "Wakad"). Omit to list every saved route.'),
            'to' => $schema->string()
                ->description('Only list people whose usual destination matches this (e.g. "Hinjewadi"). Omit to list every saved route.'),
        ];
    }
}
