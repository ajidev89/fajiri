<?php

namespace App\Enums\Withdrawal;

use App\Http\Traits\EnumTrait;

enum Status: string
{
    use EnumTrait;

    case PENDING = 'pending';

    case APPROVED = 'approved';

    case PAID = 'paid';

    case REJECTED = 'rejected';

    case CANCELLED = 'cancelled';

    public function isOpen(): bool
    {
        return in_array($this, [self::PENDING, self::APPROVED], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending review',
            self::APPROVED => 'Approved, awaiting payment',
            self::PAID => 'Paid',
            self::REJECTED => 'Rejected',
            self::CANCELLED => 'Cancelled',
        };
    }
}
