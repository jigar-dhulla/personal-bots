<?php

declare(strict_types=1);

test('the yaarpool landing page returns a successful response', function () {
    $this->get(route('yaarpool.home'))->assertStatus(200);
});

test('the yaarpool landing page shows the headline and WhatsApp call to action', function () {
    $response = $this->get(route('yaarpool.home'));

    $response->assertSee('Share rides');
    $response->assertSee('The carpool that lives in your group chat.');
    $response->assertSee('No strangers. Just yaars.');
    $response->assertSee('Add to WhatsApp group');
    $response->assertSee('https://github.com/jigar-dhulla/personal-bots', false);
});

test('the yaarpool landing page links to the usage guide', function () {
    $this->get(route('yaarpool.home'))->assertSee(route('yaarpool.usage'), false);
});

test('the yaarpool pages pick up the shared WhatsApp invite link', function () {
    config(['whatsapp-agent.number' => '15551234567']);

    $this->get(route('yaarpool.home'))->assertSee('https://wa.me/15551234567', false);
    $this->get(route('yaarpool.usage'))->assertSee('https://wa.me/15551234567', false);
});

test('the yaarpool pages fall back to an anchor when no number is configured', function () {
    config(['whatsapp-agent.number' => '']);

    $this->get(route('yaarpool.home'))->assertDontSee('https://wa.me/', false);
});
