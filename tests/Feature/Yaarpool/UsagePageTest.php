<?php

test('the usage page is public and returns a successful response', function () {
    $response = $this->get(route('yaarpool.usage'));

    $response->assertStatus(200);
});

test('the usage page shows how to use the bot with examples', function () {
    $response = $this->get(route('yaarpool.usage'));

    $response->assertSee('How to use');
    $response->assertSee('Offer a ride');
    $response->assertSee('Ask for a lift');
    $response->assertSee('Join a ride');
    $response->assertSee('Cancel your ride');
    $response->assertSee('Save your travel routine');
    $response->assertSee('Driving from Andheri to BKC tomorrow at 9am, 2 seats free 🚙');
    $response->assertSee('My office hours are 9am to 6pm, Mon/Wed/Fri.');
});

test('the pre-split /usage link still reaches the guide', function () {
    $this->get('/usage')->assertRedirect('/yaarpool/usage');
});
