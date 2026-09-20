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
    $response->assertSee('https://github.com/jigar-dhulla/personal-bots', false);
});

test('the hub carries the house brand and logo', function () {
    config(['app.name' => 'Example Bots']);

    $response = $this->get('/');

    $response->assertSee('Example Bots');
    $response->assertSee('/logo.svg', false);
});

test("a bot's own domain opens that bot's landing page instead of the hub", function () {
    config(['bots.domains' => ['yaarpool' => 'rideshare.ing']]);

    $this->get('http://rideshare.ing/')->assertRedirect('http://rideshare.ing/yaarpool');
    $this->get('http://www.rideshare.ing/')->assertRedirect('http://www.rideshare.ing/yaarpool');
});

test('a host no bot claims still gets the hub', function () {
    config(['bots.domains' => ['yaarpool' => 'rideshare.ing']]);

    $this->get('http://bots.example.test/')
        ->assertOk()
        ->assertSee('Many bots.');
});

test('the hub is served on every host when no bot claims a domain', function () {
    config(['bots.domains' => []]);

    $this->get('http://rideshare.ing/')
        ->assertOk()
        ->assertSee('Many bots.');
});

test('the hub offers a WhatsApp invite link when the number is configured', function () {
    config(['whatsapp-agent.number' => '15551234567']);

    $this->get('/')->assertSee('https://wa.me/15551234567', false);
});

test('the hub omits the WhatsApp invite link when no number is configured', function () {
    config(['whatsapp-agent.number' => '']);

    $this->get('/')->assertDontSee('https://wa.me/', false);
});
