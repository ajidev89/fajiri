<?php

namespace App\Enums\Disbursement;

use App\Http\Traits\EnumTrait;

enum RecipientType: string
{
    use EnumTrait;

    case CAMPAIGN_OWNER = 'campaign_owner';

    case INDIVIDUAL_BENEFICIARY = 'individual_beneficiary';

    case ORGANIZATION = 'organization';

    case VENDOR_SERVICE_PROVIDER = 'vendor_service_provider';

    case MULTIPLE_BENEFICIARIES = 'multiple_beneficiaries';
}
