<?php

namespace App\Http\Requests\Disbursement;

use Illuminate\Foundation\Http\FormRequest;

class SubmitDisbursementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_type'      => 'nullable|string|in:campaign_owner,individual_beneficiary,organization,vendor_service_provider,multiple_beneficiaries',
            'beneficiary_name'    => 'required|string|max:255',
            'recipient_country'   => 'nullable|string|max:10',
            'recipient_email'     => 'nullable|email|max:255',
            'recipient_phone'     => 'nullable|string|max:50',
            'amount'              => 'required|numeric|min:1',
            'payout_method'       => 'required|string',
            'fee_bearer'          => 'nullable|string|in:campaign,recipient',
            'target_currency'     => 'nullable|string|max:10',
            'account_name'        => 'nullable|string|max:255',
            'account_number'      => 'required|string|max:50',
            'bank_name'           => 'required|string|max:255',
            'bank_code'           => 'nullable|string|max:50',
            'routing_number'      => 'nullable|string|max:50',
            'swift_bic'           => 'nullable|string|max:50',
            'iban'                => 'nullable|string|max:50',
            'purpose'             => 'required|string|max:255',
            'purpose_description' => 'nullable|string|max:1000',
            'documents'           => 'nullable|array',
            'documents.*'         => 'nullable|string',
            'otp'                 => 'nullable|string',
            'password'            => 'nullable|string',
        ];
    }
}
