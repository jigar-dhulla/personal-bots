<?php

declare(strict_types=1);

use App\Bots\Yaarpool\Models\Ride;
use App\Bots\Yaarpool\Models\RidePassenger;
use App\Bots\Yaarpool\Models\UserSetting;
use App\Bots\Yaarpool\Tools\RouteTravellersTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

$chatJid = '120363409213306573@g.us';

it('lists chat members whose saved route matches the requested from and to', function () use ($chatJid) {
    Ride::factory()->create([
        'chat_jid' => $chatJid,
        'sender_jid' => '911111111111@s.whatsapp.net',
        'sender_name' => 'Bob',
    ]);

    UserSetting::factory()->create([
        'sender_jid' => '911111111111@s.whatsapp.net',
        'default_from_location' => 'Wakad',
        'default_to_location' => 'Hinjewadi',
        'office_days' => ['monday', 'wednesday', 'friday'],
    ]);

    $reply = (new RouteTravellersTool(chatJid: $chatJid))
        ->handle(new Request(['from' => 'Wakad', 'to' => 'Hinjewadi']));

    expect((string) $reply)
        ->toContain('Bob: Wakad → Hinjewadi')
        ->toContain('in office Mon/Wed/Fri');
});

it('lists everyone with a saved route when no from or to is given', function () use ($chatJid) {
    Ride::factory()->create([
        'chat_jid' => $chatJid,
        'sender_jid' => '911111111111@s.whatsapp.net',
        'sender_name' => 'Bob',
    ]);
    Ride::factory()->create([
        'chat_jid' => $chatJid,
        'sender_jid' => '912222222222@s.whatsapp.net',
        'sender_name' => 'Asha',
    ]);

    UserSetting::factory()->create([
        'sender_jid' => '911111111111@s.whatsapp.net',
        'default_from_location' => 'Wakad',
        'default_to_location' => 'Hinjewadi',
    ]);
    UserSetting::factory()->create([
        'sender_jid' => '912222222222@s.whatsapp.net',
        'default_from_location' => 'Baner',
        'default_to_location' => 'Kharadi',
    ]);

    $reply = (string) (new RouteTravellersTool(chatJid: $chatJid))->handle(new Request([]));

    expect($reply)
        ->toContain('Bob: Wakad → Hinjewadi')
        ->toContain('Asha: Baner → Kharadi');
});

it('works from saved defaults even when the person never posted a ride', function () use ($chatJid) {
    $driverRide = Ride::factory()->create([
        'chat_jid' => $chatJid,
        'sender_jid' => '917777777777@s.whatsapp.net',
        'sender_name' => 'Driver',
    ]);

    RidePassenger::factory()->create([
        'ride_id' => $driverRide->id,
        'sender_jid' => '911111111111@s.whatsapp.net',
        'sender_name' => 'Chetan',
    ]);

    UserSetting::factory()->create([
        'sender_jid' => '911111111111@s.whatsapp.net',
        'default_from_location' => 'Wakad',
        'default_to_location' => 'Hinjewadi',
    ]);

    $reply = (new RouteTravellersTool(chatJid: $chatJid))
        ->handle(new Request(['to' => 'Hinjewadi']));

    expect((string) $reply)->toContain('Chetan: Wakad → Hinjewadi');
});

it('matches locations case-insensitively and by partial text', function () use ($chatJid) {
    Ride::factory()->create([
        'chat_jid' => $chatJid,
        'sender_jid' => '911111111111@s.whatsapp.net',
        'sender_name' => 'Bob',
    ]);

    UserSetting::factory()->create([
        'sender_jid' => '911111111111@s.whatsapp.net',
        'default_from_location' => 'Wakad Station',
        'default_to_location' => 'Hinjewadi Phase 2',
    ]);

    $reply = (new RouteTravellersTool(chatJid: $chatJid))
        ->handle(new Request(['from' => 'wakad', 'to' => 'hinjewadi']));

    expect((string) $reply)->toContain('Bob: Wakad Station → Hinjewadi Phase 2');
});

it('does not surface personal defaults for someone never seen in this chat', function () use ($chatJid) {
    Ride::factory()->create([
        'chat_jid' => 'other-chat@g.us',
        'sender_jid' => '911111111111@s.whatsapp.net',
        'sender_name' => 'Bob',
    ]);

    UserSetting::factory()->create([
        'sender_jid' => '911111111111@s.whatsapp.net',
        'default_from_location' => 'Wakad',
        'default_to_location' => 'Hinjewadi',
    ]);

    $reply = (new RouteTravellersTool(chatJid: $chatJid))->handle(new Request([]));

    expect((string) $reply)->toContain('No one in this chat has saved a usual route');
});

it('reports when the requested route matches no saved defaults', function () use ($chatJid) {
    Ride::factory()->create([
        'chat_jid' => $chatJid,
        'sender_jid' => '911111111111@s.whatsapp.net',
        'sender_name' => 'Bob',
    ]);

    UserSetting::factory()->create([
        'sender_jid' => '911111111111@s.whatsapp.net',
        'default_from_location' => 'Wakad',
        'default_to_location' => 'Hinjewadi',
    ]);

    $reply = (new RouteTravellersTool(chatJid: $chatJid))
        ->handle(new Request(['from' => 'Baner']));

    expect((string) $reply)->toContain('No one in this chat has saved that route');
});
