<?php

declare(strict_types=1);

namespace Database\Factories\Yaarpool;

use App\Bots\Yaarpool\Models\Ride;
use App\Bots\Yaarpool\Models\RidePassenger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RidePassenger>
 */
class RidePassengerFactory extends Factory
{
    /** @var class-string<RidePassenger> */
    protected $model = RidePassenger::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ride_id' => Ride::factory()->offer(),
            'sender_jid' => fake()->numerify('############').'@s.whatsapp.net',
            'sender_name' => fake()->name(),
            'seats' => 1,
        ];
    }
}
