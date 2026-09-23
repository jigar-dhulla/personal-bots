<?php

declare(strict_types=1);

it('explains that the Instamart bot is private and invites forking', function () {
    $this->get(route('instamart.home'))
        ->assertSuccessful()
        ->assertSee('Private project')
        ->assertSee('not open to anyone else', false)
        ->assertSee('Fork on GitHub')
        ->assertSee('https://github.com/jigar-dhulla/personal-bots', false)
        ->assertSee('Not affiliated with or endorsed by Swiggy.');
});

it('never offers the WhatsApp number on the Instamart page', function () {
    config(['whatsapp-agent.number' => '15551234567']);

    $this->get(route('instamart.home'))->assertDontSee('https://wa.me/', false);
});

it('is served at /instamart and linked from the hub', function () {
    expect(route('instamart.home', absolute: false))->toBe('/instamart');

    $this->get(route('home'))->assertSee(route('instamart.home'), false);
});
