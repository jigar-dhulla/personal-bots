<?php

declare(strict_types=1);

use App\Bots\Yaarpool\YaarpoolAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a user from a WhatsApp sender with name and number, no email', function () {
    $user = User::registerFromWhatsApp('919999999999@s.whatsapp.net', 'Asha');

    expect(User::count())->toBe(1)
        ->and($user->name)->toBe('Asha')
        ->and($user->phone)->toBe('919999999999')
        ->and($user->email)->toBeNull()
        ->and($user->password)->toBeNull();
});

it('strips the device suffix and domain from the JID to the bare number', function () {
    $user = User::registerFromWhatsApp('919999999999:5@s.whatsapp.net', 'Asha');

    expect($user->phone)->toBe('919999999999');
});

it('does not duplicate a user for the same number', function () {
    User::registerFromWhatsApp('919999999999@s.whatsapp.net', 'Asha');
    User::registerFromWhatsApp('919999999999@s.whatsapp.net', 'Asha');

    expect(User::count())->toBe(1);
});

it('refreshes the name when a new one is provided', function () {
    User::registerFromWhatsApp('919999999999@s.whatsapp.net', 'Asha');
    $user = User::registerFromWhatsApp('919999999999@s.whatsapp.net', 'Asha Patel');

    expect(User::count())->toBe(1)
        ->and($user->fresh()->name)->toBe('Asha Patel');
});

it('keeps an existing name when a later interaction has no name', function () {
    User::registerFromWhatsApp('919999999999@s.whatsapp.net', 'Asha');
    User::registerFromWhatsApp('919999999999@s.whatsapp.net', null);

    expect(User::sole()->name)->toBe('Asha');
});

it('falls back to the number as the name when none is known', function () {
    $user = User::registerFromWhatsApp('919999999999@s.whatsapp.net');

    expect($user->name)->toBe('919999999999');
});

it('registers the participant with the name the agent receives', function () {
    app(YaarpoolAgent::class)->forChat(
        '120363409213306573@g.us',
        '919999999999@s.whatsapp.net',
        'Asha',
    );

    $user = User::sole();
    expect($user->phone)->toBe('919999999999')
        ->and($user->name)->toBe('Asha');
});

it('does not register a user when the chat has no sender', function () {
    app(YaarpoolAgent::class)->forChat('120363409213306573@g.us');

    expect(User::count())->toBe(0);
});
