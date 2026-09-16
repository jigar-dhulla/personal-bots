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
    $response->assertSee('https://github.com/jigar-dhulla/yaarpool-whatsapp-agent', false);
});

test('the yaarpool landing page links to the usage guide', function () {
    $this->get(route('yaarpool.home'))->assertSee(route('yaarpool.usage'), false);
});
