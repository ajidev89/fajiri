<?php

namespace App\Http\Requests\Disbursement;

use App\Http\Requests\ApiRequest;

class StoreRequest extends ApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'disbursable_id' => 'required|uuid',
            'disbursable_type' => 'required|string|in:campaigns,needs',
            'amount' => 'required|numeric|min:1',
            'currency' => 'nullable|string|max:3',
            'beneficiary_name' => 'required|string|max:255',
            'payment_method' => 'required|string|max:255',
            'account_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:20',
            'bank_name' => 'required|string|max:255',
        ];
    }
}
