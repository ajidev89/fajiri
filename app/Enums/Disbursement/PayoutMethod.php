<?php

namespace App\Enums\Disbursement;

use App\Http\Traits\EnumTrait;

enum PayoutMethod: string
{
    use EnumTrait;

    case LOCAL_BANK_TRANSFER = 'local_bank_transfer';

    case INTERNATIONAL_BANK_TRANSFER = 'international_bank_transfer';

    case SEPA = 'sepa';

    case ACH = 'ach';

    case SWIFT = 'swift';

    case PLATFORM_WALLET = 'platform_wallet';

    case MOBILE_MONEY = 'mobile_money';

    case DIGITAL_WALLET = 'digital_wallet';

    case CARD = 'card';
}
