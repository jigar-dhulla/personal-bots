<?php

declare(strict_types=1);

use App\Bots\Yaarpool\Enums\RideType;
use App\Bots\Yaarpool\Models\Ride;
use App\Bots\Yaarpool\Models\RidePassenger;
use App\Bots\Yaarpool\Tools\RideCreateTool;
use App\Bots\Yaarpool\Tools\RideDeleteTool;
use App\Bots\Yaarpool\Tools\RideJoinTool;
use App\Bots\Yaarpool\Tools\RideListTool;
use App\Bots\Yaarpool\Tools\RideRequestTool;
use App\Bots\Yaarpool\Tools\RideUpdateTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Carbon;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

it('persists a ride request with both raw text and parsed datetime', function () {
    $tool = new RideRequestTool(
        chatJid: '120363409213306573@g.us',
        senderJid: '919999999999@s.whatsapp.net',
        senderName: 'Asha',
    );

    $reply = $tool->handle(new Request([
        'from' => 'Andheri East',
        'to' => 'BKC',
        'when_text' => 'tomorrow 8am',
        'departs_at' => '2026-05-20T08:00:00',
        'seats' => 2,
        'notes' => 'one cabin bag',
    ]));

    expect(Ride::count())->toBe(1);

    $ride = Ride::sole();
    expect($ride->type)->toBe(RideType::Request)
        ->and($ride->chat_jid)->toBe('120363409213306573@g.us')
        ->and($ride->sender_jid)->toBe('919999999999@s.whatsapp.net')
        ->and($ride->sender_name)->toBe('Asha')
        ->and($ride->from_location)->toBe('Andheri East')
        ->and($ride->to_location)->toBe('BKC')
        ->and($ride->when_text)->toBe('tomorrow 8am')
        ->and($ride->departs_at)->toEqual(Carbon::parse('2026-05-20T08:00:00'))
        ->and($ride->seats)->toBe(2)
        ->and($ride->notes)->toBe('one cabin bag');

    expect((string) $reply)
        ->toContain('Andheri East')
        ->toContain('BKC')
        ->toContain('tomorrow 8am')
        ->toContain('2 seats');
});

it('defaults a missing seats value to 1 on a ride request', function () {
    $tool = new RideRequestTool;

    $tool->handle(new Request([
        'from' => 'A',
        'to' => 'B',
        'when_text' => 'now',
        'departs_at' => Carbon::now()->toIso8601String(),
    ]));

    expect(Ride::sole()->seats)->toBe(1);
});

it('returns a clarifying message and does not persist when departs_at is unparseable', function () {
    $tool = new RideRequestTool;

    $reply = $tool->handle(new Request([
        'from' => 'A',
        'to' => 'B',
        'when_text' => 'sometime',
        'departs_at' => 'not-a-datetime',
    ]));

    expect(Ride::count())->toBe(0)
        ->and((string) $reply)->toContain('could not work out');
});

it('persists a ride offer with price, vehicle, and parsed datetime', function () {
    $tool = new RideCreateTool(
        chatJid: '120363409213306573@g.us',
        senderJid: '918888888888@s.whatsapp.net',
    );

    $reply = $tool->handle(new Request([
        'from' => 'Pune',
        'to' => 'Mumbai',
        'when_text' => 'Sat 9:30am',
        'departs_at' => '2026-05-23T09:30:00',
        'seats' => 3,
        'price_per_seat' => '₹500',
        'vehicle' => 'Innova',
    ]));

    $ride = Ride::sole();
    expect($ride->type)->toBe(RideType::Offer)
        ->and($ride->from_location)->toBe('Pune')
        ->and($ride->to_location)->toBe('Mumbai')
        ->and($ride->when_text)->toBe('Sat 9:30am')
        ->and($ride->departs_at)->toEqual(Carbon::parse('2026-05-23T09:30:00'))
        ->and($ride->seats)->toBe(3)
        ->and($ride->price_per_seat)->toBe('₹500')
        ->and($ride->vehicle)->toBe('Innova');

    expect((string) $reply)
        ->toContain('Pune → Mumbai')
        ->toContain('Sat 9:30am')
        ->toContain('3 seats')
        ->toContain('₹500/seat');
});

it('lists upcoming rides scoped to the current chat', function () {
    $chatJid = '120363409213306573@g.us';

    Ride::factory()->request()->create([
        'chat_jid' => $chatJid,
        'from_location' => 'Andheri East',
        'to_location' => 'BKC',
        'when_text' => 'tomorrow 8am',
        'departs_at' => Carbon::now()->addDay(),
        'seats' => 2,
    ]);

    Ride::factory()->offer()->create([
        'chat_jid' => $chatJid,
        'sender_name' => 'Asha',
        'from_location' => 'Pune',
        'to_location' => 'Mumbai',
        'when_text' => 'Sat 9:30am',
        'departs_at' => Carbon::now()->addDays(3),
        'seats' => 3,
        'price_per_seat' => '₹500',
    ]);

    Ride::factory()->request()->create([
        'chat_jid' => 'other-chat@g.us',
        'from_location' => 'Delhi',
        'to_location' => 'Agra',
        'departs_at' => Carbon::now()->addDay(),
    ]);

    Ride::factory()->offer()->create([
        'chat_jid' => $chatJid,
        'from_location' => 'Bengaluru',
        'to_location' => 'Mysuru',
        'departs_at' => Carbon::now()->subDay(),
    ]);

    $reply = (string) (new RideListTool(chatJid: $chatJid))->handle(new Request([]));

    expect($reply)
        ->toContain('Andheri East')
        ->toContain('BKC')
        ->toContain('Pune → Mumbai')
        ->toContain('by Asha')
        ->toContain('₹500')
        ->not->toContain('Delhi')
        ->not->toContain('Bengaluru');
});

it('filters listings by type when specified', function () {
    $chatJid = '120363409213306573@g.us';

    Ride::factory()->request()->create([
        'chat_jid' => $chatJid,
        'from_location' => 'Andheri East',
        'to_location' => 'BKC',
        'departs_at' => Carbon::now()->addDay(),
    ]);

    Ride::factory()->offer()->create([
        'chat_jid' => $chatJid,
        'from_location' => 'Pune',
        'to_location' => 'Mumbai',
        'departs_at' => Carbon::now()->addDays(2),
    ]);

    $offersOnly = (string) (new RideListTool(chatJid: $chatJid))->handle(new Request([
        'type' => 'offer',
    ]));

    expect($offersOnly)
        ->toContain('OFFER')
        ->toContain('Pune → Mumbai')
        ->not->toContain('Andheri East');
});

it('returns a friendly message when no upcoming rides exist', function () {
    $reply = (new RideListTool(chatJid: '120363409213306573@g.us'))->handle(new Request([]));

    expect((string) $reply)->toContain('No upcoming rides');
});

it('lets the owner update their own ride', function () {
    $chatJid = '120363409213306573@g.us';
    $senderJid = '919999999999@s.whatsapp.net';

    $ride = Ride::factory()->offer()->create([
        'chat_jid' => $chatJid,
        'sender_jid' => $senderJid,
        'from_location' => 'Pune',
        'to_location' => 'Mumbai',
        'when_text' => 'Sat 9:30am',
        'departs_at' => Carbon::parse('2026-05-23T09:30:00'),
        'seats' => 3,
        'price_per_seat' => '₹500',
    ]);

    $reply = (new RideUpdateTool(chatJid: $chatJid, senderJid: $senderJid))->handle(new Request([
        'ride_id' => $ride->id,
        'seats' => 2,
        'price_per_seat' => '₹600',
    ]));

    $ride->refresh();

    expect($ride->seats)->toBe(2)
        ->and($ride->price_per_seat)->toBe('₹600')
        ->and($ride->from_location)->toBe('Pune')
        ->and((string) $reply)->toContain('updated')->toContain('2 seats');
});

it('refuses to update a ride owned by someone else', function () {
    $chatJid = '120363409213306573@g.us';

    $ride = Ride::factory()->request()->create([
        'chat_jid' => $chatJid,
        'sender_jid' => '919999999999@s.whatsapp.net',
        'from_location' => 'Andheri East',
        'to_location' => 'BKC',
        'seats' => 2,
    ]);

    $reply = (new RideUpdateTool(chatJid: $chatJid, senderJid: '911111111111@s.whatsapp.net'))->handle(new Request([
        'ride_id' => $ride->id,
        'seats' => 5,
    ]));

    expect($ride->fresh()->seats)->toBe(2)
        ->and((string) $reply)->toContain('Only the person who posted');
});

it('hides rides from other chats when updating', function () {
    $ride = Ride::factory()->offer()->create([
        'chat_jid' => 'other-chat@g.us',
        'sender_jid' => '919999999999@s.whatsapp.net',
        'seats' => 3,
    ]);

    $reply = (new RideUpdateTool(chatJid: '120363409213306573@g.us', senderJid: '919999999999@s.whatsapp.net'))->handle(new Request([
        'ride_id' => $ride->id,
        'seats' => 1,
    ]));

    expect($ride->fresh()->seats)->toBe(3)
        ->and((string) $reply)->toContain('could not find');
});

it('returns a clarifying message when the updated departs_at is unparseable', function () {
    $chatJid = '120363409213306573@g.us';
    $senderJid = '919999999999@s.whatsapp.net';

    $ride = Ride::factory()->request()->create([
        'chat_jid' => $chatJid,
        'sender_jid' => $senderJid,
        'when_text' => 'tomorrow 8am',
        'departs_at' => Carbon::parse('2026-05-20T08:00:00'),
    ]);

    $reply = (new RideUpdateTool(chatJid: $chatJid, senderJid: $senderJid))->handle(new Request([
        'ride_id' => $ride->id,
        'departs_at' => 'not-a-datetime',
    ]));

    expect($ride->fresh()->when_text)->toBe('tomorrow 8am')
        ->and((string) $reply)->toContain('could not work out');
});

it('lets the owner cancel their own ride', function () {
    $chatJid = '120363409213306573@g.us';
    $senderJid = '919999999999@s.whatsapp.net';

    $ride = Ride::factory()->request()->create([
        'chat_jid' => $chatJid,
        'sender_jid' => $senderJid,
        'from_location' => 'Andheri East',
        'to_location' => 'BKC',
    ]);

    $reply = (new RideDeleteTool(chatJid: $chatJid, senderJid: $senderJid))->handle(new Request([
        'ride_id' => $ride->id,
    ]));

    expect(Ride::find($ride->id))->toBeNull()
        ->and((string) $reply)->toContain('cancelled')
        ->toContain('Andheri East')
        ->toContain('BKC');
});

it('refuses to delete a ride owned by someone else', function () {
    $chatJid = '120363409213306573@g.us';

    $ride = Ride::factory()->offer()->create([
        'chat_jid' => $chatJid,
        'sender_jid' => '919999999999@s.whatsapp.net',
    ]);

    $reply = (new RideDeleteTool(chatJid: $chatJid, senderJid: '911111111111@s.whatsapp.net'))->handle(new Request([
        'ride_id' => $ride->id,
    ]));

    expect(Ride::find($ride->id))->not->toBeNull()
        ->and((string) $reply)->toContain('Only the person who posted');
});

it('hides rides from other chats when deleting', function () {
    $ride = Ride::factory()->offer()->create([
        'chat_jid' => 'other-chat@g.us',
        'sender_jid' => '919999999999@s.whatsapp.net',
    ]);

    $reply = (new RideDeleteTool(chatJid: '120363409213306573@g.us', senderJid: '919999999999@s.whatsapp.net'))->handle(new Request([
        'ride_id' => $ride->id,
    ]));

    expect(Ride::find($ride->id))->not->toBeNull()
        ->and((string) $reply)->toContain('could not find');
});

it('refuses to mutate rides when the sender JID is not known', function () {
    $ride = Ride::factory()->request()->create([
        'chat_jid' => '120363409213306573@g.us',
        'sender_jid' => '919999999999@s.whatsapp.net',
    ]);

    $updateReply = (new RideUpdateTool(chatJid: '120363409213306573@g.us'))->handle(new Request([
        'ride_id' => $ride->id,
        'seats' => 5,
    ]));

    $deleteReply = (new RideDeleteTool(chatJid: '120363409213306573@g.us'))->handle(new Request([
        'ride_id' => $ride->id,
    ]));

    expect((string) $updateReply)->toContain('cannot verify')
        ->and((string) $deleteReply)->toContain('cannot verify')
        ->and(Ride::find($ride->id))->not->toBeNull();
});

it('lets a participant join a ride by id and reduces the seats left', function () {
    $chatJid = '120363409213306573@g.us';

    $ride = Ride::factory()->offer()->create([
        'chat_jid' => $chatJid,
        'sender_jid' => '918888888888@s.whatsapp.net',
        'sender_name' => 'Asha',
        'from_location' => 'Pune',
        'to_location' => 'Mumbai',
        'when_text' => 'Sat 9:30am',
        'departs_at' => Carbon::now()->addDays(2),
        'seats' => 3,
    ]);

    $reply = (new RideJoinTool(
        chatJid: $chatJid,
        senderJid: '919999999999@s.whatsapp.net',
        senderName: 'Ravi',
    ))->handle(new Request([
        'ride_id' => $ride->id,
        'seats' => 2,
    ]));

    $passenger = RidePassenger::sole();
    expect($passenger->ride_id)->toBe($ride->id)
        ->and($passenger->sender_jid)->toBe('919999999999@s.whatsapp.net')
        ->and($passenger->sender_name)->toBe('Ravi')
        ->and($passenger->seats)->toBe(2)
        ->and($ride->fresh()->seatsAvailable())->toBe(1)
        ->and((string) $reply)->toContain('Joined ride #'.$ride->id)
        ->toContain('Asha')
        ->toContain('1 seat left');

    $list = (string) (new RideListTool(chatJid: $chatJid))->handle(new Request([]));
    expect($list)->toContain('1 seat left');
});

it('resolves the ride from hints when exactly one offer matches', function () {
    $chatJid = '120363409213306573@g.us';

    $ride = Ride::factory()->offer()->create([
        'chat_jid' => $chatJid,
        'sender_name' => 'Asha',
        'from_location' => 'Pune',
        'to_location' => 'Mumbai',
        'departs_at' => Carbon::now()->addDays(2),
        'seats' => 3,
    ]);

    Ride::factory()->offer()->create([
        'chat_jid' => $chatJid,
        'from_location' => 'Delhi',
        'to_location' => 'Agra',
        'departs_at' => Carbon::now()->addDays(2),
    ]);

    $reply = (new RideJoinTool(chatJid: $chatJid, senderJid: '919999999999@s.whatsapp.net'))->handle(new Request([
        'to' => 'Mumbai',
    ]));

    expect(RidePassenger::sole()->ride_id)->toBe($ride->id)
        ->and((string) $reply)->toContain('Joined ride #'.$ride->id);
});

it('lists the candidates and asks for a ride number when hints are ambiguous', function () {
    $chatJid = '120363409213306573@g.us';

    $first = Ride::factory()->offer()->create([
        'chat_jid' => $chatJid,
        'to_location' => 'Mumbai',
        'departs_at' => Carbon::now()->addDay(),
    ]);

    $second = Ride::factory()->offer()->create([
        'chat_jid' => $chatJid,
        'to_location' => 'Mumbai Airport',
        'departs_at' => Carbon::now()->addDays(2),
    ]);

    $reply = (string) (new RideJoinTool(chatJid: $chatJid, senderJid: '919999999999@s.whatsapp.net'))->handle(new Request([
        'to' => 'Mumbai',
    ]));

    expect(RidePassenger::count())->toBe(0)
        ->and($reply)->toContain('Which ride number')
        ->toContain('#'.$first->id)
        ->toContain('#'.$second->id);
});

it('reports a miss when no offer matches the hints', function () {
    $chatJid = '120363409213306573@g.us';

    Ride::factory()->offer()->create([
        'chat_jid' => $chatJid,
        'to_location' => 'Mumbai',
        'departs_at' => Carbon::now()->addDay(),
    ]);

    $reply = (string) (new RideJoinTool(chatJid: $chatJid, senderJid: '919999999999@s.whatsapp.net'))->handle(new Request([
        'to' => 'Goa',
    ]));

    expect(RidePassenger::count())->toBe(0)
        ->and($reply)->toContain('could not find a matching ride offer');
});

it('refuses to join your own ride', function () {
    $chatJid = '120363409213306573@g.us';
    $senderJid = '918888888888@s.whatsapp.net';

    $ride = Ride::factory()->offer()->create([
        'chat_jid' => $chatJid,
        'sender_jid' => $senderJid,
        'departs_at' => Carbon::now()->addDay(),
    ]);

    $reply = (new RideJoinTool(chatJid: $chatJid, senderJid: $senderJid))->handle(new Request([
        'ride_id' => $ride->id,
    ]));

    expect(RidePassenger::count())->toBe(0)
        ->and((string) $reply)->toContain('your own ride');
});

it('refuses to join the same ride twice', function () {
    $chatJid = '120363409213306573@g.us';
    $senderJid = '919999999999@s.whatsapp.net';

    $ride = Ride::factory()->offer()->create([
        'chat_jid' => $chatJid,
        'departs_at' => Carbon::now()->addDay(),
        'seats' => 3,
    ]);

    $tool = new RideJoinTool(chatJid: $chatJid, senderJid: $senderJid);

    $tool->handle(new Request(['ride_id' => $ride->id]));
    $reply = $tool->handle(new Request(['ride_id' => $ride->id]));

    expect(RidePassenger::count())->toBe(1)
        ->and((string) $reply)->toContain('already joined');
});

it('refuses to join when not enough seats are left', function () {
    $chatJid = '120363409213306573@g.us';

    $ride = Ride::factory()->offer()->create([
        'chat_jid' => $chatJid,
        'departs_at' => Carbon::now()->addDay(),
        'seats' => 2,
    ]);

    RidePassenger::factory()->create(['ride_id' => $ride->id, 'seats' => 2]);

    $partial = (string) (new RideJoinTool(chatJid: $chatJid, senderJid: '919999999999@s.whatsapp.net'))->handle(new Request([
        'ride_id' => $ride->id,
    ]));

    expect($partial)->toContain('full')
        ->and(RidePassenger::count())->toBe(1);
});

it('tells how many seats remain when asking for too many', function () {
    $chatJid = '120363409213306573@g.us';

    $ride = Ride::factory()->offer()->create([
        'chat_jid' => $chatJid,
        'departs_at' => Carbon::now()->addDay(),
        'seats' => 3,
    ]);

    RidePassenger::factory()->create(['ride_id' => $ride->id, 'seats' => 2]);

    $reply = (string) (new RideJoinTool(chatJid: $chatJid, senderJid: '919999999999@s.whatsapp.net'))->handle(new Request([
        'ride_id' => $ride->id,
        'seats' => 2,
    ]));

    expect($reply)->toContain('only has 1 seat left')
        ->and(RidePassenger::count())->toBe(1);
});

it('refuses to join a ride request', function () {
    $chatJid = '120363409213306573@g.us';

    $ride = Ride::factory()->request()->create([
        'chat_jid' => $chatJid,
        'departs_at' => Carbon::now()->addDay(),
    ]);

    $reply = (string) (new RideJoinTool(chatJid: $chatJid, senderJid: '919999999999@s.whatsapp.net'))->handle(new Request([
        'ride_id' => $ride->id,
    ]));

    expect(RidePassenger::count())->toBe(0)
        ->and($reply)->toContain('request, not an offer');
});

it('refuses to join a ride that already departed', function () {
    $chatJid = '120363409213306573@g.us';

    $ride = Ride::factory()->offer()->create([
        'chat_jid' => $chatJid,
        'departs_at' => Carbon::now()->subHour(),
    ]);

    $reply = (string) (new RideJoinTool(chatJid: $chatJid, senderJid: '919999999999@s.whatsapp.net'))->handle(new Request([
        'ride_id' => $ride->id,
    ]));

    expect(RidePassenger::count())->toBe(0)
        ->and($reply)->toContain('already departed');
});

it('hides rides from other chats when joining', function () {
    $ride = Ride::factory()->offer()->create([
        'chat_jid' => 'other-chat@g.us',
        'departs_at' => Carbon::now()->addDay(),
    ]);

    $reply = (new RideJoinTool(chatJid: '120363409213306573@g.us', senderJid: '919999999999@s.whatsapp.net'))->handle(new Request([
        'ride_id' => $ride->id,
    ]));

    expect(RidePassenger::count())->toBe(0)
        ->and((string) $reply)->toContain('could not find');
});

it('refuses to join when the sender JID is not known', function () {
    $ride = Ride::factory()->offer()->create([
        'chat_jid' => '120363409213306573@g.us',
        'departs_at' => Carbon::now()->addDay(),
    ]);

    $reply = (new RideJoinTool(chatJid: '120363409213306573@g.us'))->handle(new Request([
        'ride_id' => $ride->id,
    ]));

    expect(RidePassenger::count())->toBe(0)
        ->and((string) $reply)->toContain('cannot verify');
});

it('lists a fully joined offer as FULL', function () {
    $chatJid = '120363409213306573@g.us';

    $ride = Ride::factory()->offer()->create([
        'chat_jid' => $chatJid,
        'from_location' => 'Pune',
        'to_location' => 'Mumbai',
        'departs_at' => Carbon::now()->addDay(),
        'seats' => 2,
    ]);

    RidePassenger::factory()->create(['ride_id' => $ride->id, 'seats' => 2]);

    $list = (string) (new RideListTool(chatJid: $chatJid))->handle(new Request([]));

    expect($list)->toContain('FULL')
        ->not->toContain('seats left');
});

it('removes passengers when their ride is cancelled', function () {
    $chatJid = '120363409213306573@g.us';
    $senderJid = '918888888888@s.whatsapp.net';

    $ride = Ride::factory()->offer()->create([
        'chat_jid' => $chatJid,
        'sender_jid' => $senderJid,
        'departs_at' => Carbon::now()->addDay(),
    ]);

    RidePassenger::factory()->create(['ride_id' => $ride->id]);

    (new RideDeleteTool(chatJid: $chatJid, senderJid: $senderJid))->handle(new Request([
        'ride_id' => $ride->id,
    ]));

    expect(Ride::find($ride->id))->toBeNull()
        ->and(RidePassenger::count())->toBe(0);
});

it('exposes the expected names and schema keys to the agent', function () {
    $schema = new JsonSchemaTypeFactory;

    expect((new RideRequestTool)->name())->toBe('ride_request')
        ->and(array_keys((new RideRequestTool)->schema($schema)))
        ->toBe(['from', 'to', 'when_text', 'departs_at', 'seats', 'notes']);

    expect((new RideCreateTool)->name())->toBe('ride_create')
        ->and(array_keys((new RideCreateTool)->schema($schema)))
        ->toBe(['from', 'to', 'when_text', 'departs_at', 'seats', 'price_per_seat', 'vehicle', 'notes']);

    expect((new RideListTool)->name())->toBe('ride_list')
        ->and(array_keys((new RideListTool)->schema($schema)))
        ->toBe(['type', 'limit']);

    expect((new RideJoinTool)->name())->toBe('ride_join')
        ->and(array_keys((new RideJoinTool)->schema($schema)))
        ->toBe(['ride_id', 'from', 'to', 'poster_name', 'seats']);

    expect((new RideUpdateTool)->name())->toBe('ride_update')
        ->and(array_keys((new RideUpdateTool)->schema($schema)))
        ->toBe(['ride_id', 'from', 'to', 'when_text', 'departs_at', 'seats', 'price_per_seat', 'vehicle', 'notes']);

    expect((new RideDeleteTool)->name())->toBe('ride_delete')
        ->and(array_keys((new RideDeleteTool)->schema($schema)))
        ->toBe(['ride_id']);
});
