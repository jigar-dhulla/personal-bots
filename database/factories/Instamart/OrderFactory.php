<?php

declare(strict_types=1);

namespace Database\Factories\Instamart;

use App\Bots\Instamart\Enums\OrderStatus;
use App\Bots\Instamart\Enums\PaymentMethod;
use App\Bots\Instamart\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /** @var class-string<Order> */
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => fake()->numerify('##########'),
            'chat_jid' => fake()->numerify('############').'@s.whatsapp.net',
            'sender_jid' => fake()->numerify('############').'@s.whatsapp.net',
            'payment_method' => PaymentMethod::Cash,
            'paas_id' => null,
            'status' => OrderStatus::Placed,
            'cart_total' => (string) fake()->numberBetween(99, 1500),
        ];
    }

    public function awaitingUpi(): static
    {
        return $this->state(fn (): array => [
            'payment_method' => PaymentMethod::Upi,
            'paas_id' => fake()->uuid(),
            'status' => OrderStatus::PendingPayment,
        ]);
    }
}
