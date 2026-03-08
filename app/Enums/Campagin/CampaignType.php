<?php

namespace App\Enums\Campagin;

use App\Http\Traits\EnumTrait;

enum CampaignType: string
{
    use EnumTrait;

    case PERSONAL = 'personal';
    case ORGANIZATION = 'organization';
}
