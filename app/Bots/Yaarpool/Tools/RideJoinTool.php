<?php

declare(strict_types=1);

namespace App\Bots\Yaarpool\Tools;

use App\Bots\Yaarpool\Enums\RideType;
use App\Bots\Yaarpool\Models\Ride;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RideJoinTool implements Tool
{
    public function __construct(
        protected ?string $chatJid = null,
        protected ?string $senderJid = null,
        protected ?string $senderName = null,
    ) {}

    public function name(): string
    {
        return 'ride_join';
    }

    public function description(): Stringable|string
    {
        return 'Join a ride offer as a passenger, reserving one or more seats. Use when the user wants to take a seat on a ride someone else is offering — "count me in", "I\'ll join", "book me 2 seats on ride 7". Pass `ride_id` when the user gave a ride number or it is unambiguous from the conversation (bot replies include #id). Otherwise pass whatever hints they gave (`from`, `to`, `poster_name`) — the tool finds the ride, or lists the candidates and asks the user for a ride number.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($this->senderJid === null) {
            return 'I cannot verify who you are right now, so I can\'t add you to a ride.';
        }

        $ride = $this->resolveRide($request);

        if (is_string($ride)) {
            return $ride;
        }

        $seats = max(1, (int) ($request['seats'] ?? 1));

        if ($ride->type !== RideType::Offer) {
            return sprintf('Ride #%d is a ride request, not an offer — there are no seats to join. You could offer %s a ride instead.', $ride->id, $ride->sender_name ?? 'them');
        }

        if ($ride->sender_jid === $this->senderJid) {
            return sprintf('Ride #%d is your own ride — you are the driver, no need to join it.', $ride->id);
        }

        if ($ride->departs_at->isPast()) {
            return sprintf('Ride #%d (%s) has already departed.', $ride->id, $ride->when_text);
        }

        if ($ride->passengers()->where('sender_jid', $this->senderJid)->exists()) {
            return sprintf('You have already joined ride #%d.', $ride->id);
        }

        $available = $ride->seatsAvailable();

        if ($available < $seats) {
            return $available === 0
                ? sprintf('Sorry, ride #%d is already full.', $ride->id)
                : sprintf('Ride #%d only has %d seat%s left, not %d.', $ride->id, $available, $available === 1 ? '' : 's', $seats);
        }

        $ride->passengers()->create([
            'sender_jid' => $this->senderJid,
            'sender_name' => $this->senderName,
            'seats' => $seats,
        ]);

        $left = $available - $seats;

        return sprintf(
            'You\'re in! Joined ride #%d with %s: %s → %s, %s (%d seat%s). %s',
            $ride->id,
            $ride->sender_name ?? 'the driver',
            $ride->from_location,
            $ride->to_location,
            $ride->when_text,
            $seats,
            $seats === 1 ? '' : 's',
            $left === 0 ? 'The ride is now full.' : sprintf('%d seat%s left.', $left, $left === 1 ? '' : 's'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'ride_id' => $schema->integer()
                ->description('The numeric id of the ride to join (e.g. 7 for "ride #7"). Pass it when the user named a number or the ride is clear from recent messages. Omit it to search by the hints below instead — never guess an id.')
                ->min(1),
            'from' => $schema->string()
                ->description('Origin the user mentioned, used to find the ride when no id is known (e.g. "the Pune ride").'),
            'to' => $schema->string()
                ->description('Destination the user mentioned, used to find the ride when no id is known.'),
            'poster_name' => $schema->string()
                ->description('Name of the person offering the ride, used to find the ride when no id is known (e.g. "Asha\'s ride").'),
            'seats' => $schema->integer()
                ->description('Seats to reserve. Defaults to 1; more when the user brings company (e.g. "me +1" is 2).')
                ->min(1)
                ->max(8),
        ];
    }

    /**
     * Find the ride the user means. An explicit id wins; otherwise match the
     * given hints against upcoming offers in this chat. Exactly one match is
     * joined directly — anything else becomes a message back to the user
     * (a numbered list of candidates, or a miss).
     */
    private function resolveRide(Request $request): Ride|string
    {
        $rideId = (int) ($request['ride_id'] ?? 0);

        if ($rideId > 0) {
            $ride = Ride::query()
                ->when($this->chatJid !== null, fn ($q) => $q->where('chat_jid', $this->chatJid))
                ->find($rideId);

            return $ride ?? sprintf('I could not find ride #%d in this chat.', $rideId);
        }

        $now = Carbon::now();

        $query = Ride::query()
            ->where('type', RideType::Offer)
            ->where('departs_at', '>=', $now)
            ->when($this->chatJid !== null, fn ($q) => $q->where('chat_jid', $this->chatJid));

        foreach (['from' => 'from_location', 'to' => 'to_location', 'poster_name' => 'sender_name'] as $key => $column) {
            if (! empty($request[$key])) {
                $query->where($column, 'like', '%'.trim((string) $request[$key]).'%');
            }
        }

        $candidates = $query->get()
            ->sortBy(fn (Ride $ride): Carbon => $ride->departs_at)
            ->values();

        if ($candidates->isEmpty()) {
            return 'I could not find a matching ride offer in this chat. Ask me to list rides, or tell me the ride number.';
        }

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        $lines = $candidates->take(5)->map(fn (Ride $ride): string => sprintf(
            '#%d by %s: %s → %s, %s',
            $ride->id,
            $ride->sender_name,
            $ride->from_location,
            $ride->to_location,
            $ride->when_text,
        ));

        return "I found more than one matching ride. Which ride number would you like to join?\n".$lines->implode("\n");
    }
}
