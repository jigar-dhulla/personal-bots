<?php

declare(strict_types=1);

use App\Bots\Yaarpool\Models\GroupSetting;
use App\Bots\Yaarpool\Models\Ride;
use App\Bots\Yaarpool\Tools\RideCreateTool;
use App\Bots\Yaarpool\Tools\RideRequestTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

it('assumes the group default origin when a ride offer omits it', function () {
    $chatJid = '120363409213306573@g.us';

    GroupSetting::factory()->create([
        'chat_jid' => $chatJid,
        'default_from_location' => 'Sector 21 gate',
    ]);

    (new RideCreateTool(chatJid: $chatJid, senderJid: '918888888888@s.whatsapp.net'))->handle(new Request([
        'to' => 'Mumbai',
        'when_text' => 'today 6pm',
        'departs_at' => '2026-06-17T18:00:00',
        'seats' => 3,
    ]));

    expect(Ride::sole()->from_location)->toBe('Sector 21 gate');
});

it('assumes both group default origin and destination when a request omits them', function () {
    $chatJid = '120363409213306573@g.us';

    GroupSetting::factory()->create([
        'chat_jid' => $chatJid,
        'default_from_location' => 'Sector 21 gate',
        'default_to_location' => 'Tech Park',
    ]);

    (new RideRequestTool(chatJid: $chatJid, senderJid: '919999999999@s.whatsapp.net'))->handle(new Request([
        'when_text' => 'tomorrow 8am',
        'departs_at' => '2026-06-18T08:00:00',
    ]));

    $ride = Ride::sole();
    expect($ride->from_location)->toBe('Sector 21 gate')
        ->and($ride->to_location)->toBe('Tech Park');
});

it('prefers an explicit location over the group default', function () {
    $chatJid = '120363409213306573@g.us';

    GroupSetting::factory()->create([
        'chat_jid' => $chatJid,
        'default_from_location' => 'Sector 21 gate',
        'default_to_location' => 'Tech Park',
    ]);

    (new RideRequestTool(chatJid: $chatJid, senderJid: '919999999999@s.whatsapp.net'))->handle(new Request([
        'from' => 'Main Street',
        'when_text' => 'tomorrow 8am',
        'departs_at' => '2026-06-18T08:00:00',
    ]));

    $ride = Ride::sole();
    expect($ride->from_location)->toBe('Main Street')
        ->and($ride->to_location)->toBe('Tech Park');
});

it('asks for a pickup location when none is given and no group default exists', function () {
    $reply = (new RideRequestTool(chatJid: '120363409213306573@g.us'))->handle(new Request([
        'to' => 'BKC',
        'when_text' => 'tomorrow 8am',
        'departs_at' => '2026-06-18T08:00:00',
    ]));

    expect(Ride::count())->toBe(0)
        ->and((string) $reply)->toContain('default pickup location');
});

it('asks for a destination when none is given and no group default destination exists', function () {
    $chatJid = '120363409213306573@g.us';

    GroupSetting::factory()->create([
        'chat_jid' => $chatJid,
        'default_from_location' => 'Sector 21 gate',
        'default_to_location' => null,
    ]);

    $reply = (new RideCreateTool(chatJid: $chatJid, senderJid: '918888888888@s.whatsapp.net'))->handle(new Request([
        'when_text' => 'today 6pm',
        'departs_at' => '2026-06-17T18:00:00',
        'seats' => 2,
    ]));

    expect(Ride::count())->toBe(0)
        ->and((string) $reply)->toContain('heading to');
});

it('saves and updates group defaults through the admin command', function () {
    $chatJid = '120363409213306573@g.us';

    $this->artisan('group:settings', ['chat' => $chatJid, '--from' => 'Sector 21 gate', '--to' => 'Tech Park'])
        ->assertSuccessful();

    expect(GroupSetting::forChat($chatJid))
        ->default_from_location->toBe('Sector 21 gate')
        ->default_to_location->toBe('Tech Park');

    // Updating only the destination keeps the existing origin.
    $this->artisan('group:settings', ['chat' => $chatJid, '--to' => 'Old Town'])
        ->assertSuccessful();

    expect(GroupSetting::forChat($chatJid))
        ->default_from_location->toBe('Sector 21 gate')
        ->default_to_location->toBe('Old Town');
});

it('refuses to save without an origin when none exists yet', function () {
    $this->artisan('group:settings', ['chat' => '120363409213306573@g.us', '--to' => 'Tech Park'])
        ->assertFailed();

    expect(GroupSetting::count())->toBe(0);
});

it('clears group defaults through the admin command', function () {
    $chatJid = '120363409213306573@g.us';

    GroupSetting::factory()->create(['chat_jid' => $chatJid]);

    $this->artisan('group:settings', ['chat' => $chatJid, '--clear' => true])
        ->assertSuccessful();

    expect(GroupSetting::forChat($chatJid))->toBeNull();
});
