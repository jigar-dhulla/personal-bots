<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Models;

use App\Bots\Instamart\Enums\OrderStatus;
use App\Bots\Instamart\Enums\PaymentMethod;
use Database\Factories\Instamart\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An Instamart order the bot placed. Kept so UPI payments can be followed up
 * after checkout and so the dashboard can show what was ordered.
 *
 * @property string $order_id
 * @property string $chat_jid
 * @property string $sender_jid
 * @property PaymentMethod $payment_method
 * @property string|null $paas_id
 * @property OrderStatus $status
 * @property string|null $cart_total
 */
#[UseFactory(OrderFactory::class)]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $table = 'instamart_orders';

    protected $fillable = [
        'order_id',
        'chat_jid',
        'sender_jid',
        'payment_method',
        'paas_id',
        'status',
        'cart_total',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'status' => OrderStatus::class,
        ];
    }
}
