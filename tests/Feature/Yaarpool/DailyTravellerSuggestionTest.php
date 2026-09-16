<?php

declare(strict_types=1);

use App\Bots\Yaarpool\Models\Ride;
use App\Bots\Yaarpool\Models\RidePassenger;
use App\Bots\Yaarpool\Models\UserSetting;
use App\Bots\Yaarpool\Tools\RideCreateTool;
use App\Bots\Yaarpool\Tools\RideRequestTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

it('suggests a chat member whose saved route and office days match a new ride offer', function () {
    $chatJid = '120363409213306573@g.us';

    Ride::factory()->create([
        'chat_jid' => $chatJid,
        'sender_jid' => '911111111111@s.whatsapp.net',
        'sender_name' => 'Bob',
        'from_location' => 'Wakad',
        'to_location' => 'Hinjewadi',
    ]);

    UserSetting::factory()->create([
        'sender_jid' => '911111111111@s.whatsapp.net',
        'default_from_location' => 'Wakad',
        'default_to_location' => 'Hinjewadi',
        'office_days' => ['monday', 'wednesday', 'friday'],
    ]);

    $reply = (new RideCreateTool(chatJid: $chatJid, senderJid: '919999999999@s.whatsapp.net', senderName: 'Asha'))
        ->handle(new Request([
            'from' => 'Wakad',
            'to' => 'Hinjewadi',
            'when_text' => 'Monday 9am',
            'departs_at' => '2026-07-20T09:00:00',
            'seats' => 3,
        ]));

    expect((string) $reply)->toContain('Bob (Mon/Wed/Fri)');
});

it('does not suggest a match on a day the traveller is not usually in', function () {
    $chatJid = '120363409213306573@g.us';

    Ride::factory()->create([
        'chat_jid' => $chatJid,
        'sender_jid' => '911111111111@s.whatsapp.net',
        'sender_name' => 'Bob',
        'from_location' => 'Wakad',
        'to_location' => 'Hinjewadi',
    ]);

    UserSetting::factory()->create([
        'sender_jid' => '911111111111@s.whatsapp.net',
        'default_from_location' => 'Wakad',
        'default_to_location' => 'Hinjewadi',
        'office_days' => ['tuesday', 'thursday'],
    ]);

    // 2026-07-20 is a Monday.
    $reply = (new RideCreateTool(chatJid: $chatJid, senderJid: '919999999999@s.whatsapp.net', senderName: 'Asha'))
        ->handle(new Request([
            'from' => 'Wakad',
            'to' => 'Hinjewadi',
            'when_text' => 'Monday 9am',
            'departs_at' => '2026-07-20T09:00:00',
            'seats' => 3,
        ]));

    expect((string) $reply)->not->toContain('Matches on this route');
});

it('does not suggest a match on a different route', function () {
    $chatJid = '120363409213306573@g.us';

    Ride::factory()->create([
        'chat_jid' => $chatJid,
        'sender_jid' => '911111111111@s.whatsapp.net',
        'sender_name' => 'Bob',
        'from_location' => 'Baner',
        'to_location' => 'Hinjewadi',
    ]);

    UserSetting::factory()->create([
        'sender_jid' => '911111111111@s.whatsapp.net',
        'default_from_location' => 'Baner',
        'default_to_location' => 'Hinjewadi',
        'office_days' => ['monday'],
    ]);

    $reply = (new RideCreateTool(chatJid: $chatJid, senderJid: '919999999999@s.whatsapp.net', senderName: 'Asha'))
        ->handle(new Request([
            'from' => 'Wakad',
            'to' => 'Hinjewadi',
            'when_text' => 'Monday 9am',
            'departs_at' => '2026-07-20T09:00:00',
            'seats' => 3,
        ]));

    expect((string) $reply)->not->toContain('Matches on this route');
});

it('does not suggest a person from a different chat', function () {
    Ride::factory()->create([
        'chat_jid' => 'other-chat@g.us',
        'sender_jid' => '911111111111@s.whatsapp.net',
        'sender_name' => 'Bob',
        'from_location' => 'Wakad',
        'to_location' => 'Hinjewadi',
    ]);

    UserSetting::factory()->create([
        'sender_jid' => '911111111111@s.whatsapp.net',
        'default_from_location' => 'Wakad',
        'default_to_location' => 'Hinjewadi',
        'office_days' => ['monday'],
    ]);

    $reply = (new RideCreateTool(chatJid: '120363409213306573@g.us', senderJid: '919999999999@s.whatsapp.net', senderName: 'Asha'))
        ->handle(new Request([
            'from' => 'Wakad',
            'to' => 'Hinjewadi',
            'when_text' => 'Monday 9am',
            'departs_at' => '2026-07-20T09:00:00',
            'seats' => 3,
        ]));

    expect((string) $reply)->not->toContain('Matches on this route');
});

it('does not suggest the poster themselves', function () {
    $chatJid = '120363409213306573@g.us';
    $senderJid = '919999999999@s.whatsapp.net';

    Ride::factory()->create([
        'chat_jid' => $chatJid,
        'sender_jid' => $senderJid,
        'sender_name' => 'Asha',
        'from_location' => 'Wakad',
        'to_location' => 'Hinjewadi',
    ]);

    UserSetting::factory()->create([
        'sender_jid' => $senderJid,
        'default_from_location' => 'Wakad',
        'default_to_location' => 'Hinjewadi',
        'office_days' => ['monday'],
    ]);

    $reply = (new RideCreateTool(chatJid: $chatJid, senderJid: $senderJid, senderName: 'Asha'))
        ->handle(new Request([
            'from' => 'Wakad',
            'to' => 'Hinjewadi',
            'when_text' => 'Monday 9am',
            'departs_at' => '2026-07-20T09:00:00',
            'seats' => 3,
        ]));

    expect((string) $reply)->not->toContain('Matches on this route');
});

it('matches a chat member known only through joining a ride, not posting one', function () {
    $chatJid = '120363409213306573@g.us';

    $otherRide = Ride::factory()->create([
        'chat_jid' => $chatJid,
        'sender_jid' => '917777777777@s.whatsapp.net',
        'sender_name' => 'Driver',
    ]);

    RidePassenger::factory()->create([
        'ride_id' => $otherRide->id,
        'sender_jid' => '911111111111@s.whatsapp.net',
        'sender_name' => 'Chetan',
    ]);

    UserSetting::factory()->create([
        'sender_jid' => '911111111111@s.whatsapp.net',
        'default_from_location' => 'Wakad',
        'default_to_location' => 'Hinjewadi',
        'office_days' => UserSetting::normalizeOfficeDays(['daily']),
    ]);

    $reply = (new RideRequestTool(chatJid: $chatJid, senderJid: '919999999999@s.whatsapp.net', senderName: 'Asha'))
        ->handle(new Request([
            'from' => 'Wakad',
            'to' => 'Hinjewadi',
            'when_text' => 'Monday 9am',
            'departs_at' => '2026-07-20T09:00:00',
        ]));

    expect((string) $reply)->toContain('Chetan (daily)');
});

it('ignores a chat member with no saved office days', function () {
    $chatJid = '120363409213306573@g.us';

    Ride::factory()->create([
        'chat_jid' => $chatJid,
        'sender_jid' => '911111111111@s.whatsapp.net',
        'sender_name' => 'Bob',
        'from_location' => 'Wakad',
        'to_location' => 'Hinjewadi',
    ]);

    UserSetting::factory()->create([
        'sender_jid' => '911111111111@s.whatsapp.net',
        'default_from_location' => 'Wakad',
        'default_to_location' => 'Hinjewadi',
    ]);

    $reply = (new RideCreateTool(chatJid: $chatJid, senderJid: '919999999999@s.whatsapp.net', senderName: 'Asha'))
        ->handle(new Request([
            'from' => 'Wakad',
            'to' => 'Hinjewadi',
            'when_text' => 'Monday 9am',
            'departs_at' => '2026-07-20T09:00:00',
            'seats' => 3,
        ]));

    expect((string) $reply)->not->toContain('Matches on this route');
});
