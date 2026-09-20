<?php

declare(strict_types=1);

namespace App\Bots\Yaarpool\Enums;

enum RideType: string
{
    case Request = 'request';
    case Offer = 'offer';
}
