<?php

namespace App\Enums\Donations;

use App\Http\Traits\EnumTrait;

enum Medium: string
{
    use EnumTrait;
    
    case PAYSTACK = 'paystack';

    case WALLET = 'wallet';

    case STRIPE = 'stripe';

}