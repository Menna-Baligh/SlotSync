<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case PENDING = 'PENDING';
    case CONFIRMED = 'CONFIRMED';
    case CANCELLED = 'CANCELLED';
    case EXPIRED = 'EXPIRED';

    public function isCapacityConsuming(): bool
    {
        return match ($this) {
            self::PENDING, self::CONFIRMED => true,
            self::CANCELLED, self::EXPIRED => false,
        };
    }
}
