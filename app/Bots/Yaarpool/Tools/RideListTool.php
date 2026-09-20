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

class RideListTool implements Tool
{
    public function __construct(
        protected ?string $chatJid = null,
    ) {}

    public function name(): string
    {
        return 'ride_list';
    }

    public function description(): Stringable|string
    {
        return 'List upcoming rides that have been shared in this chat. Use this when the user asks to see existing rides, find a lift to/from a place, or check what has been posted.';
    }

    public function handle(Request $request): Stringable|string
    {
        $type = $this->normalizeType($request['type'] ?? null);
        $limit = max(1, min(20, (int) ($request['limit'] ?? 5)));
        $now = Carbon::now();

        $query = Ride::query()
            ->where('departs_at', '>=', $now);

        if ($this->chatJid !== null) {
            $query->where('chat_jid', $this->chatJid);
        }

        if ($type !== null) {
            $query->where('type', $type);
        }

        $rides = $query->get()
            ->sortBy(fn (Ride $ride): Carbon => $ride->departs_at)
            ->take($limit);

        if ($rides->isEmpty()) {
            return 'No upcoming rides found in this chat.';
        }

        return $rides->map(fn (Ride $ride) => sprintf(
            '#%d %s by %s: %s → %s, %s, %s%s',
            $ride->id,
            $ride->type === RideType::Offer ? 'OFFER' : 'REQUEST',
            $ride->sender_name,
            $ride->from_location,
            $ride->to_location,
            $ride->when_text,
            $this->seatsLabel($ride),
            $ride->price_per_seat ? ' @ '.$ride->price_per_seat : '',
        ))->implode("\n");
    }

    /**
     * Offers show how many seats are still open (joins reduce it); requests
     * show how many seats the passenger needs.
     */
    private function seatsLabel(Ride $ride): string
    {
        if ($ride->type !== RideType::Offer) {
            return sprintf('%d seat%s', $ride->seats, $ride->seats === 1 ? '' : 's');
        }

        $left = $ride->seatsAvailable();

        return $left === 0
            ? 'FULL'
            : sprintf('%d seat%s left', $left, $left === 1 ? '' : 's');
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->description('Filter by ride type. Use "request" to list passengers looking for a lift, "offer" to list drivers publishing trips. Omit to list both.'),
            'limit' => $schema->integer()
                ->description('Maximum number of rides to return. Defaults to 5.')
                ->min(1)
                ->max(20),
        ];
    }

    private function normalizeType(?string $type): ?RideType
    {
        if ($type === null) {
            return null;
        }

        return match (strtolower(trim($type))) {
            'request', 'requests' => RideType::Request,
            'offer', 'offers' => RideType::Offer,
            default => null,
        };
    }
}
