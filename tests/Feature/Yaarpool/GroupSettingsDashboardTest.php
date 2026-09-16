<?php

declare(strict_types=1);

use App\Bots\Yaarpool\Models\GroupSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JigarDhulla\LaravelWhatsApp\Services\Wacli;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->mock(Wacli::class)->shouldReceive('groups')->andReturn([])->byDefault();
});

function mockWacliGroups(array $groups): void
{
    test()->mock(Wacli::class)->shouldReceive('groups')->andReturn($groups);
}

it('lists the configured group defaults', function () {
    $setting = GroupSetting::factory()->create([
        'chat_jid' => '123456789@g.us',
        'default_from_location' => 'Copenhagen',
        'default_to_location' => 'Aarhus',
    ]);

    $this->get(route('yaarpool.group-settings.index'))
        ->assertOk()
        ->assertSee($setting->chat_jid)
        ->assertSee('Copenhagen')
        ->assertSee('Aarhus');
});

it('shows an empty state when no defaults are configured', function () {
    $this->get(route('yaarpool.group-settings.index'))
        ->assertOk()
        ->assertSee('No group defaults yet');
});

it('offers the wacli groups in a dropdown', function () {
    mockWacliGroups([
        ['JID' => '111@g.us', 'Name' => 'CPH rides'],
        ['JID' => '222@g.us', 'Name' => 'Weekend trips'],
    ]);

    $this->get(route('yaarpool.group-settings.index'))
        ->assertOk()
        ->assertSee('<select id="chat_jid" name="chat_jid"', false)
        ->assertSee('CPH rides — 111@g.us')
        ->assertSee('Weekend trips — 222@g.us')
        ->assertDontSee('<input id="chat_jid"', false);
});

it('disables dropdown options for groups that already have defaults', function () {
    GroupSetting::factory()->create(['chat_jid' => '111@g.us']);

    mockWacliGroups([
        ['JID' => '111@g.us', 'Name' => 'CPH rides'],
        ['JID' => '222@g.us', 'Name' => 'Weekend trips'],
    ]);

    $this->get(route('yaarpool.group-settings.index'))
        ->assertOk()
        ->assertSee('CPH rides — 111@g.us (already configured)');
});

it('falls back to a chat JID text input when wacli has no groups', function () {
    $this->get(route('yaarpool.group-settings.index'))
        ->assertOk()
        ->assertSee('<input id="chat_jid"', false)
        ->assertDontSee('<select id="chat_jid"', false);
});

it('saves defaults for a new chat', function () {
    $this->post(route('yaarpool.group-settings.store'), [
        'chat_jid' => '123456789@g.us',
        'default_from_location' => 'Copenhagen',
        'default_to_location' => '',
    ])
        ->assertRedirect(route('yaarpool.group-settings.index'))
        ->assertSessionHas('status');

    expect(GroupSetting::forChat('123456789@g.us'))
        ->default_from_location->toBe('Copenhagen')
        ->default_to_location->toBeNull();
});

it('rejects a duplicate chat when saving new defaults', function () {
    GroupSetting::factory()->create(['chat_jid' => '123456789@g.us']);

    $this->post(route('yaarpool.group-settings.store'), [
        'chat_jid' => '123456789@g.us',
        'default_from_location' => 'Copenhagen',
    ])->assertSessionHasErrors('chat_jid');

    expect(GroupSetting::query()->count())->toBe(1);
});

it('requires an origin when saving new defaults', function () {
    $this->post(route('yaarpool.group-settings.store'), [
        'chat_jid' => '123456789@g.us',
        'default_from_location' => '',
    ])->assertSessionHasErrors('default_from_location');

    expect(GroupSetting::query()->count())->toBe(0);
});

it('updates the defaults for an existing chat', function () {
    $setting = GroupSetting::factory()->create([
        'default_from_location' => 'Copenhagen',
        'default_to_location' => 'Aarhus',
    ]);

    $this->put(route('yaarpool.group-settings.update', $setting), [
        'default_from_location' => 'Odense',
        'default_to_location' => '',
    ])
        ->assertRedirect(route('yaarpool.group-settings.index'))
        ->assertSessionHas('status');

    expect($setting->refresh())
        ->default_from_location->toBe('Odense')
        ->default_to_location->toBeNull();
});

it('requires an origin when updating defaults', function () {
    $setting = GroupSetting::factory()->create(['default_from_location' => 'Copenhagen']);

    $this->put(route('yaarpool.group-settings.update', $setting), [
        'default_from_location' => '',
    ])->assertSessionHasErrors('default_from_location', null, 'updateSetting');

    expect($setting->refresh()->default_from_location)->toBe('Copenhagen');
});

it('clears the defaults for a chat', function () {
    $setting = GroupSetting::factory()->create();

    $this->delete(route('yaarpool.group-settings.destroy', $setting))
        ->assertRedirect(route('yaarpool.group-settings.index'))
        ->assertSessionHas('status');

    expect(GroupSetting::query()->count())->toBe(0);
});

it('redirects guests to the login screen', function () {
    auth()->logout();

    $this->get(route('yaarpool.group-settings.index'))->assertRedirect(route('login'));
});
