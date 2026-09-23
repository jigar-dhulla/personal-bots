<?php

declare(strict_types=1);

namespace Database\Factories\Instamart;

use App\Bots\Instamart\Models\Connection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Connection>
 */
class ConnectionFactory extends Factory
{
    /** @var class-string<Connection> */
    protected $model = Connection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => fake()->uuid(),
            'redirect_uri' => 'http://localhost:8765/callback',
            'access_token' => fake()->sha256(),
            'expires_at' => Carbon::now()->addDays(5),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => Carbon::now()->subHour()]);
    }
}
