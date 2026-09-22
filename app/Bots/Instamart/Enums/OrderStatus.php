<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Placed = 'placed';
    case Failed = 'failed';
}
