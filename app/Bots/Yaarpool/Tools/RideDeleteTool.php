<?php

declare(strict_types=1);

namespace App\Bots\Yaarpool\Tools;

use App\Bots\Yaarpool\Models\Ride;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RideDeleteTool implements Tool
{
    public function __construct(
        protected ?string $chatJid = null,
        protected ?string $senderJid = null,
    ) {}

    public function name(): string
    {
        return 'ride_delete';
    }

    public function description(): Stringable|string
    {
        return 'Cancel a ride that the user previously posted. Only the original poster can cancel their own ride. Use this when the user wants to cancel, remove, or withdraw a ride they shared.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($this->senderJid === null) {
            return 'I cannot verify who you are right now, so I can\'t cancel a ride.';
        }

        $rideId = (int) ($request['ride_id'] ?? 0);

        $ride = Ride::query()
            ->when($this->chatJid !== null, fn ($q) => $q->where('chat_jid', $this->chatJid))
            ->find($rideId);

        if ($ride === null) {
            return sprintf('I could not find ride #%d in this chat.', $rideId);
        }

        if ($ride->sender_jid !== $this->senderJid) {
            return sprintf('Only the person who posted ride #%d can cancel it.', $rideId);
        }

        $ride->delete();

        return sprintf('Ride #%d cancelled: %s → %s.', $rideId, $ride->from_location, $ride->to_location);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'ride_id' => $schema->integer()
                ->description('The numeric id of the ride to cancel (e.g. 7 for "ride #7"). The user usually references this from a previous bot reply or from a ride_list result.')
                ->min(1)
                ->required(),
        ];
    }
}
