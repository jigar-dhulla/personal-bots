<?php

declare(strict_types=1);

use App\Bots\Yaarpool\Models\UserSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('lists the configured user defaults', function () {
    $setting = UserSetting::factory()->create([
        'sender_jid' => '919999999999@s.whatsapp.net',
        'default_from_location' => 'Wakad',
        'default_to_location' => 'Hinjewadi',
    ]);

    $this->get(route('yaarpool.user-settings.index'))
        ->assertOk()
        ->assertSee($setting->sender_jid)
        ->assertSee('Wakad')
        ->assertSee('Hinjewadi');
});

it('shows an empty state when no defaults are configured', function () {
    $this->get(route('yaarpool.user-settings.index'))
        ->assertOk()
        ->assertSee('No user defaults yet');
});

it('saves defaults for a new user', function () {
    $this->post(route('yaarpool.user-settings.store'), [
        'sender_jid' => '919999999999@s.whatsapp.net',
        'default_from_location' => 'Wakad',
        'default_to_location' => '',
        'office_start_time' => '09:00',
        'office_end_time' => '18:00',
        'office_days' => ['monday', 'wednesday'],
    ])
        ->assertRedirect(route('yaarpool.user-settings.index'))
        ->assertSessionHas('status');

    expect(UserSetting::forSender('919999999999@s.whatsapp.net'))
        ->default_from_location->toBe('Wakad')
        ->default_to_location->toBeNull()
        ->office_start_time->toBe('09:00:00')
        ->office_end_time->toBe('18:00:00')
        ->office_days->toBe(['monday', 'wednesday']);
});

it('rejects a duplicate sender when saving new defaults', function () {
    UserSetting::factory()->create(['sender_jid' => '919999999999@s.whatsapp.net']);

    $this->post(route('yaarpool.user-settings.store'), [
        'sender_jid' => '919999999999@s.whatsapp.net',
        'default_from_location' => 'Wakad',
    ])->assertSessionHasErrors('sender_jid');

    expect(UserSetting::query()->count())->toBe(1);
});

it('requires an origin when saving new defaults', function () {
    $this->post(route('yaarpool.user-settings.store'), [
        'sender_jid' => '919999999999@s.whatsapp.net',
        'default_from_location' => '',
    ])->assertSessionHasErrors('default_from_location');

    expect(UserSetting::query()->count())->toBe(0);
});

it('updates the defaults for an existing user', function () {
    $setting = UserSetting::factory()->create([
        'default_from_location' => 'Wakad',
        'default_to_location' => 'Hinjewadi',
    ]);

    $this->put(route('yaarpool.user-settings.update', $setting), [
        'default_from_location' => 'Baner',
        'default_to_location' => '',
        'office_start_time' => '09:30',
        'office_end_time' => '18:30',
        'office_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
    ])
        ->assertRedirect(route('yaarpool.user-settings.index'))
        ->assertSessionHas('status');

    expect($setting->refresh())
        ->default_from_location->toBe('Baner')
        ->default_to_location->toBeNull()
        ->office_start_time->toBe('09:30:00')
        ->office_end_time->toBe('18:30:00')
        ->and($setting->office_days)->toHaveCount(7);
});

it('requires an origin when updating defaults', function () {
    $setting = UserSetting::factory()->create(['default_from_location' => 'Wakad']);

    $this->put(route('yaarpool.user-settings.update', $setting), [
        'default_from_location' => '',
    ])->assertSessionHasErrors('default_from_location', null, 'updateSetting');

    expect($setting->refresh()->default_from_location)->toBe('Wakad');
});

it('clears the defaults for a user', function () {
    $setting = UserSetting::factory()->create();

    $this->delete(route('yaarpool.user-settings.destroy', $setting))
        ->assertRedirect(route('yaarpool.user-settings.index'))
        ->assertSessionHas('status');

    expect(UserSetting::query()->count())->toBe(0);
});

it('redirects guests to the login screen', function () {
    auth()->logout();

    $this->get(route('yaarpool.user-settings.index'))->assertRedirect(route('login'));
});
