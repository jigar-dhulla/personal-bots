<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Upi = 'upi';

    /**
     * The payment group Swiggy's `checkout` tool expects.
     */
    public function checkoutGroup(): string
    {
        return match ($this) {
            self::Cash => 'COD',
            self::Upi => 'UPI',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash on delivery',
            self::Upi => 'UPI',
        };
    }
}
