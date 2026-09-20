<?php

declare(strict_types=1);

namespace App\Bots\Yaarpool\Tools;

use App\Bots\Yaarpool\Enums\RideType;
use App\Bots\Yaarpool\Models\GroupSetting;
use App\Bots\Yaarpool\Models\Ride;
use App\Bots\Yaarpool\Models\UserSetting;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RideRequestTool implements Tool
{
    public function __construct(
        protected ?string $chatJid = null,
        protected ?string $senderJid = null,
        protected ?string $senderName = null,
    ) {}

    public function name(): string
    {
        return 'ride_request';
    }

    public function description(): Stringable|string
    {
        return 'Publish a ride request from a passenger who is looking for a lift. Use this when the user wants to find a ride, not when they are offering one.';
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $departsAt = $request->date('departs_at');
        } catch (InvalidFormatException) {
            return 'I could not work out the date and time from that. Could you be more specific (e.g. "tomorrow 8am", "Fri 6pm")?';
        }

        $seats = max(1, (int) ($request['seats'] ?? 1));

        $userSettings = UserSetting::forSender($this->senderJid);
        $groupSettings = GroupSetting::forChat($this->chatJid);

        $from = $request['from'] ?? $userSettings?->default_from_location ?? $groupSettings?->default_from_location;
        $to = $request['to'] ?? $userSettings?->default_to_location ?? $groupSettings?->default_to_location;

        if (blank($from)) {
            return 'Where do you need to be picked up? Neither you nor this group has a default pickup location set.';
        }

        if (blank($to)) {
            return 'Where are you heading to?';
        }

        $ride = Ride::create([
            'type' => RideType::Request,
            'chat_jid' => $this->chatJid,
            'sender_jid' => $this->senderJid,
            'sender_name' => $this->senderName,
            'from_location' => (string) $from,
            'to_location' => (string) $to,
            'when_text' => (string) $request['when_text'],
            'departs_at' => $departsAt,
            'seats' => $seats,
            'notes' => $request['notes'] ?? null,
        ]);

        $suggestion = $ride->dailyTravellerSuggestion();

        return sprintf(
            'Ride request #%d noted: %s → %s, %s, %d seat%s. Drivers in the group will see it.%s',
            $ride->id,
            $ride->from_location,
            $ride->to_location,
            $ride->when_text,
            $ride->seats,
            $ride->seats === 1 ? '' : 's',
            $suggestion === null ? '' : ' '.$suggestion,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()
                ->description('Pickup location as stated by the passenger (e.g. "Andheri East", "Sector 21 gate"). Omit when the passenger does not name a pickup point — their personal default origin, or failing that the group\'s, will be assumed.'),
            'to' => $schema->string()
                ->description('Drop-off location as stated by the passenger. Omit when the passenger does not name a destination — their personal default destination, or failing that the group\'s, will be assumed if configured.'),
            'when_text' => $schema->string()
                ->description('The passenger\'s exact phrasing of when they need the ride, copied verbatim (e.g. "tomorrow 8am", "Fri evening"). Used for verification.')
                ->required(),
            'departs_at' => $schema->string()
                ->format('date-time')
                ->description('The same date/time normalized to ISO-8601 (e.g. "2026-05-19T08:00:00"). Resolve relative phrases ("tomorrow", "Friday") using the current date provided in your instructions. If the passenger only gave a vague time of day, pick a reasonable hour (morning = 09:00, evening = 18:00, night = 21:00).')
                ->required(),
            'seats' => $schema->integer()
                ->description('Number of seats the passenger needs. Defaults to 1.')
                ->min(1)
                ->max(8),
            'notes' => $schema->string()
                ->description('Any extra context the passenger shared — luggage, flexibility, contact preferences.'),
        ];
    }
}
