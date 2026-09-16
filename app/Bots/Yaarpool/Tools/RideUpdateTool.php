<?php

declare(strict_types=1);

namespace App\Bots\Yaarpool\Tools;

use App\Bots\Yaarpool\Models\Ride;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RideUpdateTool implements Tool
{
    public function __construct(
        protected ?string $chatJid = null,
        protected ?string $senderJid = null,
    ) {}

    public function name(): string
    {
        return 'ride_update';
    }

    public function description(): Stringable|string
    {
        return 'Edit a ride the user previously posted (pickup, drop-off, time, seats, price, vehicle, notes). Only the original poster can edit their own ride. Always pass `ride_id` and only the fields the user actually wants to change. If the time changes, update both `when_text` and `departs_at` so they stay in sync.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($this->senderJid === null) {
            return 'I cannot verify who you are right now, so I can\'t edit a ride.';
        }

        $rideId = (int) ($request['ride_id'] ?? 0);

        $ride = Ride::query()
            ->when($this->chatJid !== null, fn ($q) => $q->where('chat_jid', $this->chatJid))
            ->find($rideId);

        if ($ride === null) {
            return sprintf('I could not find ride #%d in this chat.', $rideId);
        }

        if ($ride->sender_jid !== $this->senderJid) {
            return sprintf('Only the person who posted ride #%d can edit it.', $rideId);
        }

        $changes = [];

        if (isset($request['from'])) {
            $changes['from_location'] = (string) $request['from'];
        }

        if (isset($request['to'])) {
            $changes['to_location'] = (string) $request['to'];
        }

        if (isset($request['when_text'])) {
            $changes['when_text'] = (string) $request['when_text'];
        }

        if (isset($request['departs_at'])) {
            try {
                $changes['departs_at'] = $request->date('departs_at');
            } catch (InvalidFormatException) {
                return 'I could not work out the new departure date and time. Could you be more specific (e.g. "tomorrow 8am", "Fri 6pm")?';
            }
        }

        if (isset($request['seats'])) {
            $changes['seats'] = max(1, (int) $request['seats']);
        }

        foreach (['price_per_seat', 'vehicle', 'notes'] as $key) {
            if (isset($request[$key])) {
                $changes[$key] = (string) $request[$key];
            }
        }

        if ($changes === []) {
            return sprintf('Ride #%d is unchanged — no fields were provided to update.', $rideId);
        }

        $ride->update($changes);

        return sprintf(
            'Ride #%d updated: %s → %s, %s, %d seat%s.',
            $ride->id,
            $ride->from_location,
            $ride->to_location,
            $ride->when_text,
            $ride->seats,
            $ride->seats === 1 ? '' : 's',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'ride_id' => $schema->integer()
                ->description('The numeric id of the ride to update (e.g. 7 for "ride #7").')
                ->min(1)
                ->required(),
            'from' => $schema->string()
                ->description('New pickup / origin location. Omit if unchanged.'),
            'to' => $schema->string()
                ->description('New drop-off / destination. Omit if unchanged.'),
            'when_text' => $schema->string()
                ->description('New verbatim time phrasing from the user. Provide this whenever `departs_at` changes so the two stay in sync.'),
            'departs_at' => $schema->string()
                ->format('date-time')
                ->description('New departure time normalized to ISO-8601 (e.g. "2026-05-20T18:00:00"). Resolve relative phrases using the current date from your instructions. Omit if unchanged.'),
            'seats' => $schema->integer()
                ->description('New seat count.')
                ->min(1)
                ->max(8),
            'price_per_seat' => $schema->string()
                ->description('New per-seat price (offers only). Omit if unchanged.'),
            'vehicle' => $schema->string()
                ->description('New vehicle detail (offers only). Omit if unchanged.'),
            'notes' => $schema->string()
                ->description('New free-form notes. Omit if unchanged.'),
        ];
    }
}
