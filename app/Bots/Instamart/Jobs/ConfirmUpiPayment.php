<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Jobs;

use App\Bots\Instamart\Enums\OrderStatus;
use App\Bots\Instamart\Models\Order;
use App\Bots\Instamart\Swiggy\InstamartClient;
use App\Bots\Instamart\Swiggy\SwiggyException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use JigarDhulla\LaravelWhatsApp\Services\Wacli;

/**
 * Follows a UPI checkout until the payment settles, then tells the chat.
 *
 * Each run makes one `check_payment_status` call (a server-held long-poll)
 * and, while the payment is still pending and the window Swiggy gave is
 * open, re-dispatches itself instead of looping — so the queue worker is
 * free for WhatsApp messages in between. Once the window closes, Swiggy
 * asks for one final `confirm_order`, which settles the order either way.
 *
 * @see https://mcp.swiggy.com/builders/docs/build/recipes/pay-with-upi
 */
class ConfirmUpiPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Payment statuses that end the flow without an order. */
    private const array FAILURE_REPLIES = [
        'failed' => 'The UPI payment for order #%s failed, so it was not placed. Ask me to place it again to retry.',
        'cancelled' => 'The UPI payment for order #%s was cancelled, so it was not placed.',
        'cart_changed' => 'The cart changed before order #%s was paid, so it was not placed. Check the cart and place it again.',
        'refund-initiated' => 'Swiggy started a refund for order #%s, so it will not be delivered.',
    ];

    public function __construct(
        public Order $order,
        public Carbon $deadline,
        public int $intervalMs,
    ) {}

    public function handle(InstamartClient $client, Wacli $wacli): void
    {
        if ($this->order->status !== OrderStatus::PendingPayment) {
            return;
        }

        try {
            $payment = $client->call('check_payment_status', [
                'paasId' => $this->order->paas_id,
                'orderId' => $this->order->order_id,
            ]);
        } catch (SwiggyException $exception) {
            if ($exception->isTransient() && Carbon::now()->lt($this->deadline)) {
                $this->pollAgain();

                return;
            }

            $wacli->send($this->order->chat_jid, sprintf('I could not check the UPI payment for order #%s. Ask me for your order status to see whether it went through.', $this->order->order_id));

            return;
        }

        $status = strtolower((string) ($payment['status'] ?? ''));

        if (($payment['isTerminalSuccess'] ?? false) === true || in_array($status, ['success', 'paid'], true)) {
            if (($payment['confirmed'] ?? false) !== true) {
                $client->call('confirm_order', ['orderId' => $this->order->order_id, 'paasId' => $this->order->paas_id]);
            }

            $this->settle($wacli, OrderStatus::Placed, sprintf('✅ Payment received. Instamart order #%s is placed.', $this->order->order_id));

            return;
        }

        if (($payment['isTerminalFailure'] ?? false) === true || isset(self::FAILURE_REPLIES[$status])) {
            $reply = self::FAILURE_REPLIES[$status] ?? self::FAILURE_REPLIES['failed'];

            $this->settle($wacli, OrderStatus::Failed, sprintf($reply, $this->order->order_id));

            return;
        }

        if (Carbon::now()->lt($this->deadline)) {
            $this->pollAgain();

            return;
        }

        $this->finalise($client, $wacli);
    }

    /**
     * The polling window closed while the payment was still pending. Swiggy
     * asks for a single `confirm_order`: it places the order if the payment
     * landed late and fails it otherwise.
     */
    private function finalise(InstamartClient $client, Wacli $wacli): void
    {
        $result = strtolower((string) ($client->call('confirm_order', [
            'orderId' => $this->order->order_id,
            'paasId' => $this->order->paas_id,
        ])['result'] ?? ''));

        if ($result === 'success') {
            $this->settle($wacli, OrderStatus::Placed, sprintf('✅ Payment received. Instamart order #%s is placed.', $this->order->order_id));

            return;
        }

        $this->settle($wacli, OrderStatus::Failed, sprintf('The UPI payment for order #%s did not complete in time, so it was not placed.', $this->order->order_id));
    }

    private function settle(Wacli $wacli, OrderStatus $status, string $reply): void
    {
        $this->order->update(['status' => $status]);

        $wacli->send($this->order->chat_jid, $reply);
    }

    private function pollAgain(): void
    {
        static::dispatch($this->order, $this->deadline, $this->intervalMs)
            ->delay(Carbon::now()->addMilliseconds($this->intervalMs));
    }
}
