<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Models;

use Database\Factories\Instamart\ChatAddressFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The Swiggy delivery address a chat shops against. Instamart searches, carts
 * and checkouts are all scoped to an address, so each chat picks one once and
 * every later tool call reuses it.
 *
 * @property string $chat_jid
 * @property string $address_id
 * @property string $address_line
 */
#[UseFactory(ChatAddressFactory::class)]
class ChatAddress extends Model
{
    /** @use HasFactory<ChatAddressFactory> */
    use HasFactory;

    protected $table = 'instamart_chat_addresses';

    protected $fillable = [
        'chat_jid',
        'address_id',
        'address_line',
    ];

    public static function forChat(?string $chatJid): ?self
    {
        if ($chatJid === null) {
            return null;
        }

        return static::query()->where('chat_jid', $chatJid)->first();
    }
}
