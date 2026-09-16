<?php

declare(strict_types=1);

use App\Bots\Yaarpool\Models\Ride;
use App\Bots\Yaarpool\Models\RidePassenger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('lists upcoming rides on the dashboard', function () {
    $ride = Ride::factory()->offer()->create([
        'from_location' => 'Copenhagen',
        'to_location' => 'Aarhus',
        'sender_name' => 'Priya',
    ]);

    $this->get(route('yaarpool.rides.index'))
        ->assertOk()
        ->assertSee('Copenhagen')
        ->assertSee('Aarhus')
        ->assertSee('Priya')
        ->assertSee('#'.$ride->id);
});

it('shows an empty state when there are no upcoming rides', function () {
    Ride::factory()->create(['departs_at' => now()->subDay()]);

    $this->get(route('yaarpool.rides.index'))
        ->assertOk()
        ->assertSee('No upcoming rides');
});

it('excludes past rides but keeps upcoming ones', function () {
    Ride::factory()->create([
        'from_location' => 'PastCity',
        'departs_at' => now()->subDay(),
    ]);

    Ride::factory()->create([
        'from_location' => 'UpcomingCity',
        'departs_at' => now()->addDay(),
    ]);

    $response = $this->get(route('yaarpool.rides.index'))->assertOk();

    $response->assertDontSee('PastCity')
        ->assertSee('UpcomingCity');
});

it('filters rides by type', function () {
    Ride::factory()->offer()->create(['from_location' => 'OfferCity']);
    Ride::factory()->request()->create(['from_location' => 'RequestCity']);

    $this->get(route('yaarpool.rides.index', ['type' => 'offer']))
        ->assertOk()
        ->assertSee('OfferCity')
        ->assertDontSee('RequestCity');
});

it('filters rides by search term', function () {
    Ride::factory()->create(['from_location' => 'Copenhagen', 'to_location' => 'Odense']);
    Ride::factory()->create(['from_location' => 'Berlin', 'to_location' => 'Munich']);

    $this->get(route('yaarpool.rides.index', ['search' => 'Copenhagen']))
        ->assertOk()
        ->assertSee('Copenhagen')
        ->assertDontSee('Berlin');
});

it('links each ride to its detail page', function () {
    $ride = Ride::factory()->create();

    $this->get(route('yaarpool.rides.index'))
        ->assertOk()
        ->assertSee(route('yaarpool.rides.show', $ride));
});

it('shows a ride with its details on the detail page', function () {
    $ride = Ride::factory()->offer()->create([
        'from_location' => 'Copenhagen',
        'to_location' => 'Aarhus',
        'sender_name' => 'Priya',
        'notes' => 'Meet at the station',
    ]);

    RidePassenger::factory()->create([
        'ride_id' => $ride->id,
        'sender_name' => 'Ravi',
        'seats' => 2,
    ]);

    $this->get(route('yaarpool.rides.show', $ride))
        ->assertOk()
        ->assertSee('Copenhagen')
        ->assertSee('Aarhus')
        ->assertSee('Priya')
        ->assertSee('Meet at the station')
        ->assertSee('Ravi')
        ->assertSee('Cancel ride');
});

it('deletes a ride from the detail page', function () {
    $ride = Ride::factory()->create();

    $this->delete(route('yaarpool.rides.destroy', $ride))
        ->assertRedirect(route('yaarpool.rides.index'));

    expect(Ride::find($ride->id))->toBeNull();
});

it('cascades passenger reservations when a ride is deleted', function () {
    $ride = Ride::factory()->offer()->create();
    $passenger = RidePassenger::factory()->create(['ride_id' => $ride->id]);

    $this->delete(route('yaarpool.rides.destroy', $ride))->assertRedirect(route('yaarpool.rides.index'));

    expect(RidePassenger::find($passenger->id))->toBeNull();
});

it('redirects guests to the login screen', function () {
    auth()->logout();

    $this->get(route('yaarpool.rides.index'))->assertRedirect(route('login'));
});

it('forbids guests from viewing or deleting a ride', function () {
    auth()->logout();
    $ride = Ride::factory()->create();

    $this->get(route('yaarpool.rides.show', $ride))->assertRedirect(route('login'));
    $this->delete(route('yaarpool.rides.destroy', $ride))->assertRedirect(route('login'));
});
