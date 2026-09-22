<?php

declare(strict_types=1);

namespace Database\Factories\Instamart;

use App\Bots\Instamart\Models\ChatAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatAddress>
 */
class ChatAddressFactory extends Factory
{
    /** @var class-string<ChatAddress> */
    protected $model = ChatAddress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_jid' => fake()->numerify('############').'@s.whatsapp.net',
            'address_id' => fake()->uuid(),
            'address_line' => fake()->streetAddress(),
        ];
    }
}
