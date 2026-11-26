<?php

return [
    // Smallest amount a member can request, in their wallet currency.
    'minimum_amount' => (float) env('WITHDRAWAL_MINIMUM_AMOUNT', 0),

    // Members must pass KYC before they can withdraw.
    'require_kyc' => (bool) env('WITHDRAWAL_REQUIRE_KYC', true),

    // Inbox that is emailed when a member submits a withdrawal request.
    'admin_email' => env('WITHDRAWAL_ADMIN_EMAIL'),
];
