<?php

declare(strict_types=1);

namespace App\Bots\Yaarpool\Enums;

enum Weekday: string
{
    case Monday = 'monday';
    case Tuesday = 'tuesday';
    case Wednesday = 'wednesday';
    case Thursday = 'thursday';
    case Friday = 'friday';
    case Saturday = 'saturday';
    case Sunday = 'sunday';

    /**
     * The three-letter label used in WhatsApp replies (e.g. "Mon").
     */
    public function short(): string
    {
        return ucfirst(substr($this->value, 0, 3));
    }
}
