<?php

namespace App\Enums\Disbursement;

use App\Http\Traits\EnumTrait;

enum Status: string
{
    use EnumTrait;

    case DRAFT = 'draft';

    case PENDING = 'pending';

    case PENDING_REVIEW = 'pending_review';

    case APPROVED = 'approved';

    case PROCESSING = 'processing';

    case SENT = 'sent';

    case COMPLETED = 'completed';

    case FAILED = 'failed';

    case REJECTED = 'rejected';

    case ON_HOLD = 'on_hold';

    case REVERSED = 'reversed';
}

