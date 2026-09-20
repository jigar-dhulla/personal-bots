<?php

declare(strict_types=1);

namespace Database\Factories\Yaarpool;

use App\Bots\Yaarpool\Enums\RideType;
use App\Bots\Yaarpool\Models\Ride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ride>
 */
class RideFactory extends Factory
{
    /** @var class-string<Ride> */
    protected $model = Ride::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $departsAt = fake()->dateTimeBetween('+1 hour', '+2 weeks');

        return [
            'type' => fake()->randomElement(RideType::cases()),
            'chat_jid' => fake()->numerify('############').'@g.us',
            'sender_jid' => fake()->numerify('############').'@s.whatsapp.net',
            'sender_name' => fake()->name(),
            'from_location' => fake()->city(),
            'to_location' => fake()->city(),
            'when_text' => fake()->randomElement(['tomorrow 8am', 'tonight 9pm', 'Fri evening', 'Sat 10:30am']),
            'departs_at' => $departsAt,
            'seats' => fake()->numberBetween(1, 4),
            'price_per_seat' => null,
            'vehicle' => null,
            'notes' => null,
        ];
    }

    public function request(): static
    {
        return $this->state(fn () => ['type' => RideType::Request]);
    }

    public function offer(): static
    {
        return $this->state(fn () => [
            'type' => RideType::Offer,
            'price_per_seat' => '₹'.fake()->numberBetween(50, 500),
            'vehicle' => fake()->randomElement(['Swift', 'Innova', 'Activa', 'XUV700']),
        ]);
    }
}
