<?php

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('the hub introduces the number and links to each bot', function () {
    $response = $this->get('/');

    $response->assertSee('One WhatsApp number.');
    $response->assertSee('Many bots.');
    $response->assertSee('Yaarpool');
    $response->assertSee(route('yaarpool.home'), false);
    $response->assertSee('https://github.com/jigar-dhulla/yaarpool-whatsapp-agent', false);
});

test('the hub offers a WhatsApp invite link when the number is configured', function () {
    config(['whatsapp-agent.number' => '15551234567']);

    $this->get('/')->assertSee('https://wa.me/15551234567', false);
});

test('the hub omits the WhatsApp invite link when no number is configured', function () {
    config(['whatsapp-agent.number' => '']);

    $this->get('/')->assertDontSee('https://wa.me/', false);
});
