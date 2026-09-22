<?php

declare(strict_types=1);

use App\Bots\Instamart\Enums\OrderStatus;
use App\Bots\Instamart\Jobs\ConfirmUpiPayment;
use App\Bots\Instamart\Models\Connection;
use App\Bots\Instamart\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use JigarDhulla\LaravelWhatsApp\Services\Wacli;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    Connection::factory()->create();
    $this->order = Order::factory()->awaitingUpi()->create(['order_id' => '888', 'paas_id' => 'paas-1']);
});

function runUpiJob(Order $order, Carbon $deadline, ?string $expectedReply): void
{
    $wacli = mock(Wacli::class);

    if ($expectedReply === null) {
        $wacli->shouldNotReceive('send');
    } else {
        $wacli->shouldReceive('send')->once()->withArgs(fn (string $jid, string $message) => $jid === $order->chat_jid && str_contains($message, $expectedReply))->andReturn([true, '', '']);
    }

    app()->call([new ConfirmUpiPayment($order, $deadline, 3000), 'handle']);
}

it('confirms the order once the payment succeeds', function () {
    fakeInstamart(['check_payment_status' => ['status' => 'success', 'isTerminalSuccess' => true, 'confirmed' => false]]);

    runUpiJob($this->order, Carbon::now()->addMinute(), 'Instamart order #888 is placed');

    expect(instamartCalls('confirm_order'))->toBe([['orderId' => '888', 'paasId' => 'paas-1']]);
    expect($this->order->fresh()->status)->toBe(OrderStatus::Placed);
});

it('does not confirm again when Swiggy already confirmed', function () {
    fakeInstamart(['check_payment_status' => ['status' => 'paid', 'isTerminalSuccess' => true, 'confirmed' => true]]);

    runUpiJob($this->order, Carbon::now()->addMinute(), 'is placed');

    expect(instamartCalls('confirm_order'))->toBeEmpty();
});

it('reports a failed payment without confirming', function () {
    fakeInstamart(['check_payment_status' => ['status' => 'cart_changed', 'isTerminalFailure' => true]]);

    runUpiJob($this->order, Carbon::now()->addMinute(), 'The cart changed');

    expect(instamartCalls('confirm_order'))->toBeEmpty();
    expect($this->order->fresh()->status)->toBe(OrderStatus::Failed);
});

it('polls again while the payment is pending', function () {
    Queue::fake();
    fakeInstamart(['check_payment_status' => ['status' => 'pending', 'terminal' => false]]);

    runUpiJob($this->order, Carbon::now()->addMinute(), null);

    Queue::assertPushed(ConfirmUpiPayment::class);
    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

it('settles with a final confirm once the polling window closes', function () {
    fakeInstamart([
        'check_payment_status' => ['status' => 'pending', 'terminal' => false],
        'confirm_order' => ['orderId' => '888', 'result' => 'failed'],
    ]);

    runUpiJob($this->order, Carbon::now()->subSecond(), 'did not complete in time');

    expect(instamartCalls('confirm_order'))->toHaveCount(1);
    expect($this->order->fresh()->status)->toBe(OrderStatus::Failed);
});
